import 'dart:async';

import 'package:flutter/material.dart';

import '../network/api_client.dart';
import 'vendor_mockup_ui.dart';

/// Bandeau « Hors connexion » posé au-dessus de toute l'application.
///
/// Il apparaît dès qu'une requête n'atteint pas le serveur OVANIE et disparaît
/// à la première réponse reçue. Tant qu'il est visible, le serveur est
/// recontacté toutes les 15 secondes ; le vendeur peut aussi réessayer.
class OfflineBanner extends StatefulWidget {
  const OfflineBanner({super.key, required this.child});
  final Widget child;

  @override
  State<OfflineBanner> createState() => _OfflineBannerState();
}

class _OfflineBannerState extends State<OfflineBanner> {
  Timer? _retry;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    ApiClient.offline.addListener(_changed);
  }

  @override
  void dispose() {
    ApiClient.offline.removeListener(_changed);
    _retry?.cancel();
    super.dispose();
  }

  void _changed() {
    _retry?.cancel();
    if (ApiClient.offline.value) {
      _retry = Timer.periodic(const Duration(seconds: 15), (_) => _check());
    }
    if (mounted) setState(() {});
  }

  Future<void> _check() async {
    if (_checking) return;
    setState(() => _checking = true);
    await ApiClient.ping();
    if (mounted) setState(() => _checking = false);
  }

  @override
  Widget build(BuildContext context) {
    final offline = ApiClient.offline.value;
    final top = MediaQuery.paddingOf(context).top;
    return Column(
      children: [
        AnimatedSize(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          child: offline
              ? Material(
                  color: Mk.navy,
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(16, top + 6, 8, 6),
                    child: Row(
                      children: [
                        const Icon(Icons.wifi_off_rounded, color: Colors.white, size: 20),
                        const SizedBox(width: 10),
                        const Expanded(
                          child: Text(
                            'Hors connexion. Les informations affichées peuvent ne pas être à jour.',
                            style: TextStyle(color: Colors.white, fontSize: 12.5, height: 1.3, fontWeight: FontWeight.w600),
                          ),
                        ),
                        TextButton(
                          onPressed: _checking ? null : _check,
                          style: TextButton.styleFrom(foregroundColor: Mk.orange),
                          child: _checking
                              ? const SizedBox.square(
                                  dimension: 16,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Mk.orange),
                                )
                              : const Text('Réessayer', style: TextStyle(fontWeight: FontWeight.w800)),
                        ),
                      ],
                    ),
                  ),
                )
              : const SizedBox(width: double.infinity),
        ),
        Expanded(
          child: offline ? MediaQuery.removePadding(context: context, removeTop: true, child: widget.child) : widget.child,
        ),
      ],
    );
  }
}
