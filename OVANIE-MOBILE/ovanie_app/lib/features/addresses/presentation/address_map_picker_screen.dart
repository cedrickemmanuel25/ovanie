import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart' show LatLng;

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/device_location.dart';
import '../../checkout/data/geo_repository.dart';

class AddressMapPickerScreen extends StatefulWidget {
  final double? initialLatitude;
  final double? initialLongitude;

  const AddressMapPickerScreen({super.key, this.initialLatitude, this.initialLongitude});

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

  LatLng get _initial => LatLng(widget.initialLatitude ?? 5.359952, widget.initialLongitude ?? -4.008256);

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
    final top = MediaQuery.paddingOf(context).top;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: Column(
          children: [
            Expanded(
              child: Stack(
                children: [
                  FlutterMap(
                    mapController: _mapController,
                    options: MapOptions(
                      initialCenter: _initial,
                      initialZoom: widget.initialLatitude != null ? 16 : 13,
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
                              width: 60,
                              height: 64,
                              alignment: Alignment.topCenter,
                              child: const _Pin(),
                            ),
                          ],
                        ),
                    ],
                  ),
                  if (_selected == null)
                    const Align(
                      alignment: Alignment(0, -0.25),
                      child: _HintBubble(text: 'Touchez la carte pour placer\nle point de livraison.'),
                    ),
                  Positioned(
                    left: 14,
                    top: top + 10,
                    child: Row(
                      children: [
                        Material(
                          color: Colors.white,
                          shape: const CircleBorder(),
                          elevation: 3,
                          shadowColor: const Color(0x33000000),
                          child: IconButton(
                            onPressed: () => Navigator.maybePop(context),
                            tooltip: 'Retour',
                            icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 20),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(99),
                            boxShadow: const [BoxShadow(color: Color(0x26000000), blurRadius: 8, offset: Offset(0, 2))],
                          ),
                          child: const Text(
                            'Choisir sur la carte',
                            style: TextStyle(color: _navy, fontSize: 16, fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Positioned(
                    right: 14,
                    bottom: 18,
                    child: FloatingActionButton.small(
                      heroTag: 'address-map-my-position',
                      onPressed: _myPosition,
                      tooltip: 'Ma position',
                      backgroundColor: Colors.white,
                      foregroundColor: _navy,
                      child: const Icon(Icons.gps_fixed_rounded),
                    ),
                  ),
                ],
              ),
            ),
            Container(
              width: double.infinity,
              padding: EdgeInsets.fromLTRB(16, 16, 16, 12 + MediaQuery.paddingOf(context).bottom),
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
                boxShadow: [BoxShadow(color: Color(0x1A0A1B3D), blurRadius: 16, offset: Offset(0, -4))],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Adresse sélectionnée', style: TextStyle(color: Color(0xFF5E6B80), fontSize: 13)),
                  const SizedBox(height: 4),
                  if (_loading)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 9),
                      child: LinearProgressIndicator(color: OvanieColors.orange, backgroundColor: Color(0xFFFFE6D5)),
                    )
                  else if (_error != null)
                    Text(_error!, style: const TextStyle(color: OvanieColors.danger, fontSize: 14, height: 1.35))
                  else
                    Text(
                      _resolved?.displayName ?? 'Aucun point choisi pour le moment.',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: _resolved == null ? const Color(0xFF9AA5B5) : _navy,
                        fontSize: _resolved == null ? 15 : 17,
                        fontWeight: _resolved == null ? FontWeight.w500 : FontWeight.w800,
                        height: 1.3,
                      ),
                    ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: FilledButton(
                      onPressed: _resolved == null || _loading ? null : () => Navigator.of(context).pop(_resolved),
                      style: FilledButton.styleFrom(
                        backgroundColor: OvanieColors.orange,
                        disabledBackgroundColor: const Color(0xFFFFC9A6),
                        foregroundColor: Colors.white,
                        disabledForegroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      child: const Text(
                        'Utiliser cette adresse',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                      ),
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

const _navy = Color(0xFF0A1B3D);

class _Pin extends StatelessWidget {
  const _Pin();

  @override
  Widget build(BuildContext context) {
    return Stack(
      alignment: Alignment.bottomCenter,
      children: [
        Container(
          width: 40,
          height: 14,
          decoration: BoxDecoration(
            color: OvanieColors.orange.withValues(alpha: 0.25),
            borderRadius: const BorderRadius.all(Radius.elliptical(40, 14)),
          ),
        ),
        const Positioned(top: 0, child: Icon(Icons.location_on_rounded, color: OvanieColors.orange, size: 54)),
      ],
    );
  }
}

class _HintBubble extends StatelessWidget {
  const _HintBubble({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            boxShadow: const [BoxShadow(color: Color(0x26000000), blurRadius: 10, offset: Offset(0, 3))],
          ),
          child: Text(
            text,
            textAlign: TextAlign.center,
            style: const TextStyle(color: _navy, fontSize: 14, fontWeight: FontWeight.w600, height: 1.35),
          ),
        ),
        CustomPaint(size: const Size(16, 8), painter: _ArrowPainter()),
      ],
    );
  }
}

class _ArrowPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final path = Path()
      ..moveTo(0, 0)
      ..lineTo(size.width, 0)
      ..lineTo(size.width / 2, size.height)
      ..close();
    canvas.drawPath(path, Paint()..color = Colors.white);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
