import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../app/theme.dart';
import '../domain/delivery_tracking_model.dart';

/// Carte du suivi : trajet du livreur jusqu'à l'adresse quand sa position est
/// connue, sinon l'adresse de livraison seule (jamais une position inventée).
class LiveDeliveryMap extends StatelessWidget {
  final ClientShipmentTracking? shipment;
  final double bottomInset;

  const LiveDeliveryMap({super.key, required this.shipment, this.bottomInset = 0});

  static const _abidjan = LatLng(5.345317, -4.024429);

  @override
  Widget build(BuildContext context) {
    final current = shipment;
    final destination = current?.destinationLatitude != null && current?.destinationLongitude != null
        ? LatLng(current!.destinationLatitude!, current.destinationLongitude!)
        : null;
    final driver = current?.hasLiveMap == true ? LatLng(current!.driverLatitude!, current.driverLongitude!) : null;
    final route = current?.hasLiveMap == true
        ? current!.routePoints.map((point) => LatLng(point.latitude, point.longitude)).toList(growable: false)
        : const <LatLng>[];

    final points = <LatLng>[if (driver != null) driver, if (destination != null) destination, ...route];
    final MapOptions options;
    if (points.length >= 2) {
      options = MapOptions(
        initialCameraFit: CameraFit.coordinates(
          coordinates: points,
          padding: EdgeInsets.fromLTRB(60, 110, 60, 40 + bottomInset),
          maxZoom: 16,
        ),
        minZoom: 4,
        maxZoom: 19,
      );
    } else {
      options = MapOptions(
        initialCenter: destination ?? _abidjan,
        initialZoom: destination == null ? 11.5 : 14.5,
        minZoom: 4,
        maxZoom: 19,
      );
    }

    return Stack(
      children: [
        FlutterMap(
          options: options,
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.ovanie.ovanie_app',
            ),
            if (route.length >= 2)
              PolylineLayer(
                polylines: [Polyline(points: route, strokeWidth: 5, color: OvanieColors.navy)],
              ),
            MarkerLayer(
              markers: [
                if (destination != null)
                  Marker(
                    point: destination,
                    width: 46,
                    height: 46,
                    alignment: Alignment.topCenter,
                    child: const Icon(
                      Icons.location_on_rounded,
                      color: OvanieColors.orange,
                      size: 46,
                      shadows: [Shadow(color: Color(0x40000000), blurRadius: 6)],
                    ),
                  ),
                if (driver != null)
                  Marker(
                    point: driver,
                    width: 60,
                    height: 60,
                    child: Container(
                      decoration: BoxDecoration(
                        color: OvanieColors.navy,
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 3),
                        boxShadow: const [BoxShadow(color: Color(0x33000000), blurRadius: 10, offset: Offset(0, 4))],
                      ),
                      child: const Icon(Icons.delivery_dining_rounded, color: Colors.white, size: 32),
                    ),
                  ),
              ],
            ),
          ],
        ),
        Positioned(
          left: 10,
          bottom: bottomInset + 8,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .85),
              borderRadius: BorderRadius.circular(6),
            ),
            child: const Text(
              '© OpenStreetMap contributors',
              style: TextStyle(color: OvanieColors.muted, fontSize: 10),
            ),
          ),
        ),
      ],
    );
  }
}
