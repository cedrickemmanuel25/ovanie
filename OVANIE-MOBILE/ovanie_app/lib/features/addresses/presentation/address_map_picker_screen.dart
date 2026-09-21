import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/device_location.dart';
import '../../checkout/data/geo_repository.dart';

class AddressMapPickerScreen extends StatefulWidget {
  final double? initialLatitude;
  final double? initialLongitude;

  const AddressMapPickerScreen({
    super.key,
    this.initialLatitude,
    this.initialLongitude,
  });

  @override
  State<AddressMapPickerScreen> createState() => _AddressMapPickerScreenState();
}

class _AddressMapPickerScreenState extends State<AddressMapPickerScreen> {
  final _geo = const GeoRepository();
  final _mapController = MapController();
  LatLng? _selected;
  GeoResolvedPlace? _resolved;
  bool _loading = false;
  String? _error;

  LatLng get _initial => LatLng(
        widget.initialLatitude ?? 5.359952,
        widget.initialLongitude ?? -4.008256,
      );

  @override
  void initState() {
    super.initState();
    if (widget.initialLatitude != null && widget.initialLongitude != null) {
      _selected = _initial;
      WidgetsBinding.instance.addPostFrameCallback((_) => _resolve(_initial));
    }
  }

  Future<void> _resolve(LatLng point) async {
    setState(() {
      _selected = point;
      _loading = true;
      _error = null;
    });
    try {
      final place = await _geo.reverse(latitude: point.latitude, longitude: point.longitude, fresh: true);
      if (!mounted) return;
      setState(() => _resolved = place);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _myPosition() async {
    if (_loading) return;
    try {
      final position = await DeviceLocation.currentPosition();
      final point = LatLng(position.latitude, position.longitude);
      _mapController.move(point, 16);
      await _resolve(point);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(22, 16, 22, 14),
              child: Row(
                children: [
                  IconButton(
                    onPressed: () => Navigator.maybePop(context),
                    icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF061A56), size: 30),
                  ),
                  const SizedBox(width: 8),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Choisir sur la carte',
                          style: TextStyle(color: Color(0xFF061A56), fontSize: 24, fontWeight: FontWeight.w900),
                        ),
                        SizedBox(height: 3),
                        Text(
                          'Touchez la carte pour placer le point de livraison.',
                          style: TextStyle(color: Color(0xFF53658F), fontSize: 12.5),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Stack(
                children: [
                  FlutterMap(
                    mapController: _mapController,
                    options: MapOptions(
                      initialCenter: _initial,
                      initialZoom: widget.initialLatitude != null ? 16 : 12.5,
                      minZoom: 4,
                      maxZoom: 19,
                      onTap: (_, point) => _resolve(point),
                    ),
                    children: [
                      TileLayer(
                        urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        userAgentPackageName: 'com.ovanie.ovanie_app',
                      ),
                      if (_selected != null)
                        MarkerLayer(
                          markers: [
                            Marker(
                              point: _selected!,
                              width: 56,
                              height: 56,
                              child: const Icon(Icons.location_on_rounded, color: Color(0xFF0A50EB), size: 52),
                            ),
                          ],
                        ),
                    ],
                  ),
                  Positioned(
                    right: 18,
                    top: 18,
                    child: FloatingActionButton.small(
                      heroTag: 'address-map-my-position',
                      onPressed: _myPosition,
                      backgroundColor: Colors.white,
                      foregroundColor: const Color(0xFF0A50EB),
                      child: const Icon(Icons.my_location_rounded),
                    ),
                  ),
                ],
              ),
            ),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(22, 16, 22, 20),
              decoration: const BoxDecoration(
                color: Colors.white,
                boxShadow: [BoxShadow(color: Color(0x17061A56), blurRadius: 16, offset: Offset(0, -4))],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (_loading)
                    const LinearProgressIndicator(color: Color(0xFF0A50EB))
                  else if (_error != null)
                    Text(_error!, style: const TextStyle(color: OvanieColors.danger, fontWeight: FontWeight.w700))
                  else if (_resolved != null) ...[
                    const Text('Adresse sélectionnée', style: TextStyle(color: Color(0xFF061A56), fontWeight: FontWeight.w900)),
                    const SizedBox(height: 5),
                    Text(
                      _resolved!.displayName,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Color(0xFF53658F), height: 1.35),
                    ),
                  ] else
                    const Text(
                      'Sélectionnez un point sur la carte.',
                      style: TextStyle(color: Color(0xFF53658F)),
                    ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    height: 54,
                    child: FilledButton(
                      onPressed: _resolved == null || _loading ? null : () => Navigator.of(context).pop(_resolved),
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF0A50EB),
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                      ),
                      child: const Text('Utiliser cette adresse', style: TextStyle(fontWeight: FontWeight.w900)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
