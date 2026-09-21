import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../app/theme.dart';
import '../domain/delivery_tracking_model.dart';

class LiveDeliveryMap extends StatelessWidget {
  final ClientShipmentTracking shipment;
  final double height;

  const LiveDeliveryMap({
    super.key,
    required this.shipment,
    this.height = 300,
  });

  @override
  Widget build(BuildContext context) {
    if (!shipment.hasLiveMap) {
      return const SizedBox.shrink();
    }

    final driver = LatLng(shipment.driverLatitude!, shipment.driverLongitude!);
    final destination = LatLng(shipment.destinationLatitude!, shipment.destinationLongitude!);
    final route = shipment.routePoints
        .map((point) => LatLng(point.latitude, point.longitude))
        .toList(growable: false);
    final center = LatLng(
      (driver.latitude + destination.latitude) / 2,
      (driver.longitude + destination.longitude) / 2,
    );

    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: SizedBox(
        height: height,
        child: Stack(
          children: [
            FlutterMap(
              options: MapOptions(
                initialCenter: center,
                initialZoom: 13.5,
                minZoom: 4,
                maxZoom: 19,
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.ovanie.ovanie_app',
                ),
                if (route.length >= 2)
                  PolylineLayer(
                    polylines: [
                      Polyline(
                        points: route,
                        strokeWidth: 5,
                        color: OvanieColors.blue,
                      ),
                    ],
                  ),
                MarkerLayer(
                  markers: [
                    Marker(
                      point: destination,
                      width: 46,
                      height: 46,
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                          border: Border.all(color: OvanieColors.orange, width: 2),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x22000000),
                              blurRadius: 8,
                              offset: Offset(0, 3),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.location_on_rounded,
                          color: OvanieColors.orange,
                          size: 27,
                        ),
                      ),
                    ),
                    Marker(
                      point: driver,
                      width: 54,
                      height: 54,
                      child: Container(
                        decoration: BoxDecoration(
                          color: OvanieColors.navy,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 3),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x33000000),
                              blurRadius: 10,
                              offset: Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Transform.rotate(
                          angle: ((shipment.driverHeading ?? 0) * math.pi) / 180,
                          child: const Icon(
                            Icons.navigation_rounded,
                            color: Colors.white,
                            size: 27,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            Positioned(
              right: 8,
              bottom: 7,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .9),
                  borderRadius: BorderRadius.circular(7),
                ),
                child: const Text(
                  '© OpenStreetMap contributors',
                  style: TextStyle(
                    color: OvanieColors.muted,
                    fontSize: 8.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
