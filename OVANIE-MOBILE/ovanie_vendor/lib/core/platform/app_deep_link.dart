import 'dart:async';

import 'package:flutter/services.dart';

/// Pont Android -> Flutter déjà exposé par MainActivity.
/// L'application Vendeur utilise un schéma distinct pour ne pas ouvrir
/// accidentellement l'application Client lors d'une récupération de compte.
class VendorDeepLinkBridge {
  VendorDeepLinkBridge._();

  static const MethodChannel _channel = MethodChannel('ovanie/deep_link');
  static final VendorDeepLinkBridge instance = VendorDeepLinkBridge._();

  final StreamController<Uri> _links = StreamController<Uri>.broadcast();
  bool _started = false;

  Stream<Uri> get links => _links.stream;

  Future<Uri?> start() async {
    if (!_started) {
      _started = true;
      _channel.setMethodCallHandler((call) async {
        if (call.method != 'onDeepLink') return;
        _emit(call.arguments?.toString());
      });
    }

    try {
      final raw = await _channel.invokeMethod<String>('getInitialLink');
      return _parse(raw);
    } catch (_) {
      return null;
    }
  }

  void _emit(String? raw) {
    final uri = _parse(raw);
    if (uri != null && !_links.isClosed) _links.add(uri);
  }

  Uri? _parse(String? raw) {
    final value = raw?.trim() ?? '';
    if (value.isEmpty) return null;
    final uri = Uri.tryParse(value);
    if (uri == null || uri.scheme.toLowerCase() != 'ovanie-vendeur') return null;
    return uri;
  }
}
