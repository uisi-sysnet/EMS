@include('layouts.header')
@include('layouts.topbar')

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Manage Stations</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <!-- Form to create a new station -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Add New Station</h2>
        <form action="{{ route('stations.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Station MN -->
                <div>
                    <label for="station_mn" class="block text-sm font-medium text-gray-700">Station MN *</label>
                    <input type="text" id="station_mn" name="station_mn" value="{{ old('station_mn') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('station_mn') border-red-500 @enderror">
                    @error('station_mn')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Station Name -->
                <div>
                    <label for="station_name" class="block text-sm font-medium text-gray-700">Station Name</label>
                    <input type="text" id="station_name" name="station_name" value="{{ old('station_name') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('station_name') border-red-500 @enderror">
                    @error('station_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Enabled -->
                <div>
                    <label for="enabled" class="block text-sm font-medium text-gray-700">Enabled</label>
                    <select id="enabled" name="enabled"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('enabled') border-red-500 @enderror">
                        <option value="1" {{ old('enabled') == '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ old('enabled') == '0' ? 'selected' : '' }}>No</option>
                    </select>
                    @error('enabled')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Latitude -->
                <div>
                    <label for="latitude" class="block text-sm font-medium text-gray-700">Latitude</label>
                    <input type="number" step="any" id="latitude" name="latitude" value="{{ old('latitude') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('latitude') border-red-500 @enderror">
                    @error('latitude')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Longitude -->
                <div>
                    <label for="longitude" class="block text-sm font-medium text-gray-700">Longitude</label>
                    <input type="number" step="any" id="longitude" name="longitude" value="{{ old('longitude') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('longitude') border-red-500 @enderror">
                    @error('longitude')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Lead IP -->
                <div>
                    <label for="lead_ip" class="block text-sm font-medium text-gray-700">Lead IP</label>
                    <input type="text" id="lead_ip" name="lead_ip" value="{{ old('lead_ip') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('lead_ip') border-red-500 @enderror">
                    @error('lead_ip')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Lead Port -->
                <div>
                    <label for="lead_port" class="block text-sm font-medium text-gray-700">Lead Port</label>
                    <input type="number" id="lead_port" name="lead_port" value="{{ old('lead_port') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('lead_port') border-red-500 @enderror">
                    @error('lead_port')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Lead Slave -->
                <div>
                    <label for="lead_slave" class="block text-sm font-medium text-gray-700">Lead Slave</label>
                    <input type="number" id="lead_slave" name="lead_slave" value="{{ old('lead_slave') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('lead_slave') border-red-500 @enderror">
                    @error('lead_slave')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                    Create Station
                </button>
            </div>
        </form>
    </div>

    <!-- List existing stations -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Existing Stations</h2>
        @if($stations->count())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MN</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enabled</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Latitude</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Longitude</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead IP</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead Port</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead Slave</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated At</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($stations as $station)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->station_mn }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->station_name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->enabled ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->latitude }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->longitude }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->lead_ip }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->lead_port }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->lead_slave }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $station->updated_at ? $station->updated_at->format('Y-m-d H:i') : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500">No stations found.</p>
        @endif
    </div>
</div>

@include('layouts.footer')   {{-- if you have one --}}