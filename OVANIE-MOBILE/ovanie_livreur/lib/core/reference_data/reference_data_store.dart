import 'package:flutter/foundation.dart';

import '../network/api_client.dart';

class OvanieReferenceOption {
  const OvanieReferenceOption({required this.code, required this.label});

  final String code;
  final String label;

  factory OvanieReferenceOption.fromMap(Map<dynamic, dynamic> map) {
    final code = '${map['code'] ?? map['value'] ?? map['id'] ?? ''}'.trim();
    final label = '${map['label'] ?? map['name'] ?? code}'.trim();
    return OvanieReferenceOption(code: code, label: label);
  }
}

/// Cache mémoire des référentiels Laravel communs Web ↔ Mobile.
///
/// Étape 7 : Laravel est la seule source métier. Aucune liste métier locale
/// n'est utilisée en secours. En cas d'indisponibilité, les écrans doivent
/// afficher un état d'erreur plutôt qu'une ancienne valeur divergente.
class OvanieReferenceDataStore {
  OvanieReferenceDataStore._();

  static final OvanieReferenceDataStore instance = OvanieReferenceDataStore._();

  Map<String, dynamic> _data = const <String, dynamic>{};
  Map<String, dynamic> _meta = const <String, dynamic>{};
  Future<void>? _inFlight;
  Object? _lastError;

  bool get loaded => _data.isNotEmpty;
  bool get unavailable => !loaded;
  Object? get lastError => _lastError;
  String get source => loaded ? 'laravel' : 'unavailable';
  String get schemaVersion => '${_meta['schema_version'] ?? ''}'.trim();
  String get registryVersion => '${_meta['registry_version'] ?? ''}'.trim();

  Future<void> warmUp({bool force = false}) {
    if (!force && loaded) return Future<void>.value();
    if (!force && _inFlight != null) return _inFlight!;
    final future = _load();
    _inFlight = future;
    return future.whenComplete(() {
      if (identical(_inFlight, future)) _inFlight = null;
    });
  }

  Future<void> _load() async {
    try {
      final response = await ApiClient.dio.get<dynamic>(
        '/mobile/v1/reference-data',
      );
      ApiClient.ensureSuccess(response);
      final body = response.data;
      if (body is! Map || body['data'] is! Map) {
        _lastError = StateError('Payload reference-data invalide.');
        return;
      }
      _data = Map<String, dynamic>.from(body['data'] as Map);
      _meta = body['meta'] is Map
          ? Map<String, dynamic>.from(body['meta'] as Map)
          : const <String, dynamic>{};
      _lastError = null;
      if (kDebugMode) {
        debugPrint(
          '[OVANIE references] source=laravel schema=$schemaVersion '
          'registry=$registryVersion keys=${_data.length}',
        );
      }
    } catch (error) {
      if (kDebugMode) {
        debugPrint('[OVANIE references] indisponible, sans fallback local: $error');
      }
      _lastError = error;
    }
  }

  List<OvanieReferenceOption> options(String key) {
    final raw = _data[key];
    if (raw is! List) return const <OvanieReferenceOption>[];
    final result = <OvanieReferenceOption>[];
    for (final item in raw) {
      if (item is! Map) continue;
      final option = OvanieReferenceOption.fromMap(item);
      if (option.code.isEmpty || option.label.isEmpty) continue;
      result.add(option);
    }
    return result;
  }

  List<Map<String, dynamic>> records(String key) {
    final raw = _data[key];
    if (raw is! List) return const <Map<String, dynamic>>[];
    return raw
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
  }

  List<String> codes(String key) => options(key)
      .map((item) => item.code)
      .where((value) => value.isNotEmpty)
      .toList(growable: false);

  List<String> strings(String key) {
    final raw = _data[key];
    if (raw is! List) return const <String>[];
    final result = <String>[];
    for (final item in raw) {
      if (item is Map) {
        final value = '${item['name'] ?? item['label'] ?? item['value'] ?? item['code'] ?? ''}'.trim();
        if (value.isNotEmpty) result.add(value);
        continue;
      }
      final value = '$item'.trim();
      if (value.isNotEmpty) result.add(value);
    }
    return result;
  }

  Map<String, String> optionMap(String key) => <String, String>{
        for (final item in options(key)) item.code: item.label,
      };

  String label(String key, String code, {String? fallback}) {
    final normalized = code.trim();
    for (final item in options(key)) {
      if (item.code == normalized) return item.label;
    }
    return fallback ?? code;
  }

  List<String> get communeNames => records('communes')
      .map((item) => '${item['name'] ?? ''}'.trim())
      .where((value) => value.isNotEmpty)
      .toList(growable: false);

  Map<String, List<String>> get quartersByCommuneName {
    final result = <String, List<String>>{};
    for (final commune in records('communes')) {
      final name = '${commune['name'] ?? ''}'.trim();
      if (name.isEmpty) continue;
      final rawQuarters = commune['quarters'];
      if (rawQuarters is! List) continue;
      final quarters = rawQuarters
          .whereType<Map>()
          .map((item) => '${item['name'] ?? ''}'.trim())
          .where((value) => value.isNotEmpty)
          .toList(growable: false);
      result[name] = quarters;
    }
    return result;
  }
}
