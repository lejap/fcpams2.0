<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Family & App Users GPS Map</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body, html {
            height: 100%;
            width: 100%;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            overflow: hidden;
        }

        #app-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
        }

        /* Top Header Bar */
        header {
            height: 60px;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
        }

        .brand-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: #f8fafc;
        }

        .active-badge {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 1.8s infinite;
        }

        @keyframes pulse-green {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        /* Map Container */
        #map {
            flex: 1;
            width: 100%;
            height: calc(100vh - 60px);
            z-index: 1;
        }

        /* Custom Popup Styles */
        .leaflet-popup-content-wrapper {
            background: #1e293b;
            color: #f8fafc;
            border-radius: 14px;
            border: 1px solid #334155;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            padding: 4px;
        }

        .leaflet-popup-tip {
            background: #1e293b;
        }

        .popup-card {
            padding: 8px 12px;
        }

        .popup-name {
            font-size: 15px;
            font-weight: 700;
            color: #38bdf8;
            margin-bottom: 4px;
        }

        .popup-info {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 2px;
        }

        .popup-badge {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 8px;
            background: #064e3b;
            color: #34d399;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
        }

        /* Custom Pulse Marker CSS */
        .user-marker-icon {
            background-color: #0ea5e9;
            border: 3px solid #ffffff;
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(14, 165, 233, 0.8);
        }
    </style>
</head>
<body>

<div id="app-container">
    <header>
        <div class="brand-title">
            <span>🗺️ Live Family &amp; User GPS Radar</span>
        </div>
        <div class="active-badge">
            <span class="pulse-dot"></span>
            <span id="active-count-text">0 Active Users Online</span>
        </div>
    </header>

    <div id="map"></div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // Initialize Map centered at Manila (Default view, updates automatically)
    const map = L.map('map').setView([14.5995, 120.9842], 12);

    // Dark Tile Layer (CartoDB Dark Matter)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);

    let markers = {};
    let isFirstLoad = true;

    async function fetchActiveDevices() {
        try {
            const response = await fetch('get_active_devices.php');
            const data = await response.json();

            if (data.status === 'success') {
                const activeDevices = data.devices || [];
                document.getElementById('active-count-text').innerText = `${activeDevices.length} Active User(s) Online`;

                const currentIds = new Set();
                const boundsGroup = [];

                activeDevices.forEach(device => {
                    const id = device.device_id;
                    const lat = device.latitude;
                    const lng = device.longitude;
                    const name = device.device_name || 'Active User';
                    const ago = device.seconds_ago !== undefined ? `${device.seconds_ago}s ago` : 'Just now';

                    currentIds.add(id);
                    boundsGroup.push([lat, lng]);

                    const popupContent = `
                        <div class="popup-card">
                            <div class="popup-name">📱 ${name}</div>
                            <div class="popup-info">📍 <strong>Lat:</strong> ${lat.toFixed(6)}</div>
                            <div class="popup-info">📍 <strong>Lng:</strong> ${lng.toFixed(6)}</div>
                            <div class="popup-info">⏱️ <strong>Last Active:</strong> ${ago}</div>
                            <span class="popup-badge">🟢 ONLINE &amp; TRACKING</span>
                        </div>
                    `;

                    if (markers[id]) {
                        // Smoothly move existing marker
                        markers[id].setLatLng([lat, lng]);
                        markers[id].getPopup().setContent(popupContent);
                    } else {
                        // Create custom pulse icon
                        const customIcon = L.divIcon({
                            className: 'user-marker-icon',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        });

                        const marker = L.marker([lat, lng], { icon: customIcon })
                            .bindPopup(popupContent)
                            .addTo(map);

                        markers[id] = marker;
                    }
                });

                // Remove offline markers
                Object.keys(markers).forEach(id => {
                    if (!currentIds.has(id)) {
                        map.removeLayer(markers[id]);
                        delete markers[id];
                    }
                });

                // Auto-center on first load if users exist
                if (isFirstLoad && boundsGroup.length > 0) {
                    if (boundsGroup.length === 1) {
                        map.setView(boundsGroup[0], 15);
                    } else {
                        map.fitBounds(boundsGroup, { padding: [50, 50] });
                    }
                    isFirstLoad = false;
                }
            }
        } catch (error) {
            console.error("Error fetching live active devices:", error);
        }
    }

    // Initial fetch and poll every 3 seconds
    fetchActiveDevices();
    setInterval(fetchActiveDevices, 3000);
</script>

</body>
</html>
