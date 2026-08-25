<?php

namespace App\Services\Onvif;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Minimal ONVIF SOAP client — only implements what the camera dashboard
 * needs: resolving the media service address, enumerating profiles, and
 * resolving a profile's RTSP stream URI. Deliberately not a general
 * ONVIF library (no PTZ, no WS-Discovery, no events) — the PHP ONVIF
 * package ecosystem is thin and mostly unmaintained, and this is a small
 * enough surface (two real SOAP calls) to own directly rather than trust
 * to an abandoned dependency.
 */
class OnvifClient
{
    private ?string $mediaServiceUrl = null;

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