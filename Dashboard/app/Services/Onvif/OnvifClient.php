<?php

namespace App\Services\Onvif;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Minimal ONVIF SOAP client — implements what the camera dashboard needs:
 * resolving the media service address, enumerating profiles, resolving a
 * profile's RTSP stream URI, and PTZ continuous-move/stop. Deliberately
 * not a general ONVIF library (no WS-Discovery, no events) — the PHP
 * ONVIF package ecosystem is thin and mostly unmaintained, and this is a
 * small enough surface to own directly rather than trust to an abandoned
 * dependency.
 */
class OnvifClient
{
    private ?string $mediaServiceUrl = null;
    private ?string $ptzServiceUrl = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeoutSeconds = 8,
    ) {}

    private function deviceServiceUrl(): string
    {
        return "http://{$this->host}:{$this->port}/onvif/device_service";
    }

    /**
     * @return array<int, array{token: string, name: string}>
     */
    public function getProfiles(): array
    {
        $body = $this->envelope(<<<XML
            <GetProfiles xmlns="http://www.onvif.org/ver10/media/wsdl"/>
            XML);

        $xml = $this->post($this->mediaServiceUrl(), $body, 'GetProfiles');

        $profiles = [];
        foreach ($xml->xpath('//*[local-name()="Profiles"]') as $profile) {
            $token = (string) $profile->attributes()->token;
            $nameNodes = $profile->xpath('.//*[local-name()="Name"]');
            $name = $nameNodes ? (string) $nameNodes[0] : $token;
            if ($token !== '') {
                $profiles[] = ['token' => $token, 'name' => $name];
            }
        }

        if (empty($profiles)) {
            throw new RuntimeException('Camera returned no media profiles.');
        }

        return $profiles;
    }

    public function getStreamUri(string $profileToken): string
    {
        $body = $this->envelope(<<<XML
            <GetStreamUri xmlns="http://www.onvif.org/ver10/media/wsdl">
                <StreamSetup>
                    <Stream xmlns="http://www.onvif.org/ver10/schema">RTP-Unicast</Stream>
                    <Transport xmlns="http://www.onvif.org/ver10/schema">
                        <Protocol>RTSP</Protocol>
                    </Transport>
                </StreamSetup>
                <ProfileToken>{$profileToken}</ProfileToken>
            </GetStreamUri>
            XML);

        $xml = $this->post($this->mediaServiceUrl(), $body, 'GetStreamUri');

        $uriNodes = $xml->xpath('//*[local-name()="Uri"]');
        if (empty($uriNodes)) {
            throw new RuntimeException('Camera did not return a stream URI.');
        }

        return (string) $uriNodes[0];
    }

    /**
     * Starts a continuous PTZ move at the given normalized pan/tilt/zoom
     * speeds (each in [-1, 1]; 0 means "don't move on that axis"). The
     * camera keeps moving until stop() is called — ONVIF ContinuousMove
     * has no built-in duration, this mirrors the joystick-style
     * hold-to-move / release-to-stop pattern the live view uses.
     */
    public function continuousMove(string $profileToken, float $pan, float $tilt, float $zoom = 0.0): void
    {
        $pan = $this->clamp($pan);
        $tilt = $this->clamp($tilt);
        $zoom = $this->clamp($zoom);

        $body = $this->envelope(<<<XML
            <ContinuousMove xmlns="http://www.onvif.org/ver20/ptz/wsdl">
                <ProfileToken>{$profileToken}</ProfileToken>
                <Velocity>
                    <PanTilt xmlns="http://www.onvif.org/ver10/schema" x="{$pan}" y="{$tilt}"/>
                    <Zoom xmlns="http://www.onvif.org/ver10/schema" x="{$zoom}"/>
                </Velocity>
            </ContinuousMove>
            XML);

        $this->post($this->ptzServiceUrl(), $body, 'ContinuousMove');
    }

    /**
     * Stops any in-progress PTZ move on the given profile.
     */
    public function stop(string $profileToken, bool $panTilt = true, bool $zoom = true): void
    {
        $panTiltStr = $panTilt ? 'true' : 'false';
        $zoomStr = $zoom ? 'true' : 'false';

        $body = $this->envelope(<<<XML
            <Stop xmlns="http://www.onvif.org/ver20/ptz/wsdl">
                <ProfileToken>{$profileToken}</ProfileToken>
                <PanTilt>{$panTiltStr}</PanTilt>
                <Zoom>{$zoomStr}</Zoom>
            </Stop>
            XML);

        $this->post($this->ptzServiceUrl(), $body, 'Stop');
    }

    private function clamp(float $value): string
    {
        // sprintf rather than raw string interpolation so the decimal
        // separator is always "." regardless of server locale — some
        // ONVIF PTZ implementations reject a comma decimal (e.g. "0,5").
        return sprintf('%.4f', max(-1.0, min(1.0, $value)));
    }

    /**
     * Same discover-then-fall-back pattern as mediaServiceUrl(), but for
     * the PTZ service (Category=PTZ) rather than Media.
     */
    private function ptzServiceUrl(): string
    {
        if ($this->ptzServiceUrl !== null) {
            return $this->ptzServiceUrl;
        }

        try {
            $body = $this->envelope(<<<XML
                <GetCapabilities xmlns="http://www.onvif.org/ver10/device/wsdl">
                    <Category>PTZ</Category>
                </GetCapabilities>
                XML);

            $xml = $this->post($this->deviceServiceUrl(), $body, 'GetCapabilities');
            $xAddrNodes = $xml->xpath('//*[local-name()="PTZ"]/*[local-name()="XAddr"]');

            if (! empty($xAddrNodes)) {
                return $this->ptzServiceUrl = (string) $xAddrNodes[0];
            }
        } catch (Throwable) {
            // fall through to the conventional path below
        }

        return $this->ptzServiceUrl = "http://{$this->host}:{$this->port}/onvif/ptz_service";
    }

    /**
     * ONVIF technically requires discovering the real media service
     * address via GetCapabilities rather than assuming a path. Almost
     * every Profile S device also answers on the conventional
     * /onvif/media_service path, so that's used as a fallback if
     * capability discovery fails for any reason.
     */
    private function mediaServiceUrl(): string
    {
        if ($this->mediaServiceUrl !== null) {
            return $this->mediaServiceUrl;
        }

        try {
            $body = $this->envelope(<<<XML
                <GetCapabilities xmlns="http://www.onvif.org/ver10/device/wsdl">
                    <Category>Media</Category>
                </GetCapabilities>
                XML);

            $xml = $this->post($this->deviceServiceUrl(), $body, 'GetCapabilities');
            $xAddrNodes = $xml->xpath('//*[local-name()="Media"]/*[local-name()="XAddr"]');

            if (! empty($xAddrNodes)) {
                return $this->mediaServiceUrl = (string) $xAddrNodes[0];
            }
        } catch (Throwable) {
            // fall through to the conventional path below
        }

        return $this->mediaServiceUrl = "http://{$this->host}:{$this->port}/onvif/media_service";
    }

    private function post(string $url, string $envelope, string $action): SimpleXMLElement
    {
        $response = Http::withHeaders(['Content-Type' => 'application/soap+xml; charset=utf-8'])
            ->timeout($this->timeoutSeconds)
            ->withBody($envelope, 'application/soap+xml')
            ->post($url);

        if ($response->failed()) {
            throw new RuntimeException("ONVIF {$action} failed: HTTP {$response->status()}");
        }

        $xml = @simplexml_load_string($response->body());
        if ($xml === false) {
            throw new RuntimeException("ONVIF {$action} returned unparseable XML.");
        }

        $fault = $xml->xpath('//*[local-name()="Fault"]');
        if (! empty($fault)) {
            throw new RuntimeException("ONVIF {$action} returned a SOAP fault: " . $fault[0]->asXML());
        }

        return $xml;
    }

    /**
     * Wraps a body in a SOAP envelope with a WS-Security UsernameToken
     * (PasswordDigest) — the auth scheme ONVIF Profile S devices expect.
     */
    private function envelope(string $body): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');
        $nonceRaw = random_bytes(16);
        $nonceB64 = base64_encode($nonceRaw);
        $digest = base64_encode(sha1($nonceRaw . $created . $this->password, true));

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
                <s:Header>
                    <Security xmlns="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                        <UsernameToken>
                            <Username>{$this->username}</Username>
                            <Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">{$digest}</Password>
                            <Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">{$nonceB64}</Nonce>
                            <Created xmlns="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">{$created}</Created>
                        </UsernameToken>
                    </Security>
                </s:Header>
                <s:Body>
                    {$body}
                </s:Body>
            </s:Envelope>
            XML;
    }
}