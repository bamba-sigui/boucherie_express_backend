<x-filament-panels::page>
    <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700" style="height: 600px;">
        <div id="courier-map" style="height:100%; width:100%;"></div>
    </div>

    @push('scripts')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const map = L.map('courier-map').setView([5.3599517, -4.0082563], 12); // Abidjan

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            const couriers = @json($this->getCouriersData());

            couriers.forEach(function (courier) {
                const marker = L.marker([courier.lat, courier.lng]).addTo(map);

                let popupContent = `<strong>${courier.name}</strong>`;
                if (courier.order) {
                    popupContent += `<br>Cmd #${courier.order.id}<br>${courier.order.customer ?? ''}<br>${courier.order.address ?? ''}`;
                }
                if (courier.lastUpdate) {
                    popupContent += `<br><em>Màj : ${courier.lastUpdate}</em>`;
                }

                marker.bindPopup(popupContent);
            });

            if (couriers.length === 0) {
                const info = document.createElement('div');
                info.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1000;background:white;padding:1rem;border-radius:0.5rem;';
                info.textContent = 'Aucun livreur actif avec position GPS';
                document.getElementById('courier-map').appendChild(info);
            }
        });
    </script>
    @endpush
</x-filament-panels::page>
