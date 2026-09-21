import 'dart:convert';

import 'package:dio/dio.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/device_location.dart';
import '../../../core/utils/text_cleaner.dart';

class GeoSearchResult {
  final double latitude;
  final double longitude;
  final String displayName;

  const GeoSearchResult({
    required this.latitude,
    required this.longitude,
    required this.displayName,
  });

  factory GeoSearchResult.fromJson(Map<String, dynamic> json) {
    double asDouble(dynamic value) {
      if (value is num) return value.toDouble();
      return double.tryParse('${value ?? ''}') ?? 0;
    }

    return GeoSearchResult(
      latitude: asDouble(json['latitude']),
      longitude: asDouble(json['longitude']),
      displayName: cleanOvanieText((json['display_name'] ?? '').toString()),
    );
  }
}

class GeoResolvedPlace {
  final String displayName;
  final String zone;
  final String commune;
  final String quartier;
  final String city;
  final String localityType;
  final String landmark;
  final String source;
  final double latitude;
  final double longitude;

  const GeoResolvedPlace({
    required this.displayName,
    required this.zone,
    required this.commune,
    required this.quartier,
    required this.city,
    this.localityType = '',
    this.landmark = '',
    this.source = '',
    required this.latitude,
    required this.longitude,
  });

  bool get hasHumanReadableLocation {
    final display = displayName.trim();
    final normalizedDisplay = display.toLowerCase();
    final c = commune.trim().toLowerCase();
    final q = quartier.trim();
    final currentLabelIsGeneric = display.isEmpty ||
        normalizedDisplay == 'position actuelle détectée' ||
        normalizedDisplay == 'position actuelle detectee' ||
        normalizedDisplay == 'position actuelle';

    if (currentLabelIsGeneric) return false;
    if (q.isNotEmpty) return true;
    if (c.isNotEmpty && c != 'abidjan') return true;

    // Une rue/adresse détaillée peut être exploitable même lorsque le fournisseur
    // ne sait pas nommer la commune séparément.
    return display.contains(',') &&
        (normalizedDisplay.contains('rue') ||
            normalizedDisplay.contains('avenue') ||
            normalizedDisplay.contains('boulevard') ||
            normalizedDisplay.contains('cité') ||
            normalizedDisplay.contains('cite') ||
            normalizedDisplay.contains('résidence') ||
            normalizedDisplay.contains('residence'));
  }

  bool get hasDeliveryLevelLocation {
    if (!hasHumanReadableLocation) return false;
    if (zone == 'abidjan' && commune.trim().toLowerCase() == 'abidjan') {
      return false;
    }
    if (quartier.trim().isNotEmpty) return true;
    return detailScore >= 5;
  }

  int get detailScore {
    var score = 0;
    if (city.trim().isNotEmpty) score += 1;
    if (commune.trim().isNotEmpty && commune.trim().toLowerCase() != 'abidjan') {
      score += 2;
    }
    if (quartier.trim().isNotEmpty) score += 3;
    final display = displayName.trim().toLowerCase();
    if (display.isNotEmpty && !display.contains('position actuelle')) score += 1;
    if (display.contains('rue') ||
        display.contains('avenue') ||
        display.contains('boulevard') ||
        display.contains('cité') ||
        display.contains('cite') ||
        display.contains('résidence') ||
        display.contains('residence')) {
      score += 3;
    }
    return score;
  }

  factory GeoResolvedPlace.fromJson(
    Map<String, dynamic> json, {
    required double fallbackLatitude,
    required double fallbackLongitude,
    String fallbackDisplayName = '',
  }) {
    Map<String, dynamic> mapOf(dynamic value) {
      if (value is Map) return Map<String, dynamic>.from(value);
      return <String, dynamic>{};
    }

    String text(dynamic value) => cleanOvanieText((value ?? '').toString());
    String firstNonEmpty(Iterable<String> values) {
      for (final value in values) {
        final clean = value.trim();
        if (clean.isNotEmpty) return clean;
      }
      return '';
    }

    String normalize(String value) {
      return value
          .toLowerCase()
          .replaceAll('é', 'e')
          .replaceAll('è', 'e')
          .replaceAll('ê', 'e')
          .replaceAll('ë', 'e')
          .replaceAll('à', 'a')
          .replaceAll('â', 'a')
          .replaceAll('ä', 'a')
          .replaceAll('î', 'i')
          .replaceAll('ï', 'i')
          .replaceAll('ô', 'o')
          .replaceAll('ö', 'o')
          .replaceAll('ù', 'u')
          .replaceAll('û', 'u')
          .replaceAll('ü', 'u')
          .replaceAll(RegExp(r'[^a-z0-9]+'), '');
    }

    String canonicalCommune(String value) {
      final key = normalize(value);
      const known = <String, String>{
        'abobo': 'Abobo',
        'adjame': 'Adjamé',
        'adjam': 'Adjamé',
        'anyama': 'Anyama',
        'attecoube': 'Attécoubé',
        'attecoub': 'Attécoubé',
        'bingerville': 'Bingerville',
        'cocody': 'Cocody',
        'koumassi': 'Koumassi',
        'marcory': 'Marcory',
        'plateau': 'Plateau',
        'portbouet': 'Port-Bouët',
        'songon': 'Songon',
        'treichville': 'Treichville',
        'yopougon': 'Yopougon',
      };
      return known[key] ?? value.trim();
    }

    String distinctFrom(String candidate, Iterable<String> others) {
      final clean = candidate.trim();
      if (clean.isEmpty) return '';
      final normalized = normalize(clean);
      if (normalized.isEmpty) return '';
      for (final other in others) {
        if (normalized == normalize(other)) return '';
      }
      return clean;
    }

    final address = mapOf(json['address']);
    final resolved = mapOf(json['resolved_location']);

    final rawCity = firstNonEmpty([
      text(address['city']),
      text(address['town']),
      text(address['village']),
      text(address['county']),
    ]);

    final rawAdministrativeCommune = firstNonEmpty([
      text(address['city_district']),
      text(address['municipality']),
      text(address['district']),
    ]);

    final resolvedCommuneRaw = text(resolved['commune']);
    final resolvedCommune = resolvedCommuneRaw.isEmpty
        ? ''
        : canonicalCommune(resolvedCommuneRaw);
    final resolvedCity = text(resolved['city']);
    final resolvedZone = text(resolved['zone']).toLowerCase();

    final rawDisplay = firstNonEmpty([
      text(json['display_name']),
      fallbackDisplayName,
    ]);

    const abidjanCommunes = <String>{
      'abobo',
      'adjame',
      'anyama',
      'attecoube',
      'bingerville',
      'cocody',
      'koumassi',
      'marcory',
      'plateau',
      'portbouet',
      'songon',
      'treichville',
      'yopougon',
    };
    final communeKey = normalize(resolvedCommune);
    final administrativeKey = normalize(rawAdministrativeCommune);
    final isAbidjan = resolved['is_abidjan'] == true ||
        resolvedZone == 'abidjan' ||
        abidjanCommunes.contains(communeKey) ||
        abidjanCommunes.contains(administrativeKey) ||
        rawCity.toLowerCase().contains('abidjan') ||
        rawDisplay.toLowerCase().contains('abidjan');

    final zone = isAbidjan ? 'abidjan' : 'interieur';
    final communeCandidate = isAbidjan
        ? firstNonEmpty([resolvedCommune, rawAdministrativeCommune])
        : firstNonEmpty([
            resolvedCommune,
            rawAdministrativeCommune,
            resolvedCity,
            rawCity,
          ]);
    final commune = canonicalCommune(communeCandidate);
    final city = isAbidjan
        ? 'Abidjan'
        : firstNonEmpty([resolvedCity, rawCity, rawAdministrativeCommune, commune]);

    final quarterCandidate = firstNonEmpty([
      text(resolved['quartier']),
      text(address['neighbourhood']),
      text(address['neighborhood']),
      text(address['quarter']),
      text(address['residential']),
      text(address['locality']),
      text(address['suburb']),
    ]);

    final landmarkCandidate = firstNonEmpty([
      text(resolved['repere']),
      text(address['amenity']),
      text(address['building']),
      text(address['shop']),
      text(address['office']),
      text(address['tourism']),
      text(address['road']),
      text(json['feature_name']),
    ]);

    final quarter = distinctFrom(
      quarterCandidate,
      [commune, city, 'Abidjan', "Côte d'Ivoire", "Cote d'Ivoire"],
    );
    final landmark = distinctFrom(
      landmarkCandidate,
      [commune, city, quarter, 'Abidjan', "Côte d'Ivoire", "Cote d'Ivoire"],
    );
    final quartier = quarter.isNotEmpty ? quarter : landmark;

    final houseNumber = text(address['house_number']);
    final road = text(address['road']);
    final composedAddress = <String>[
      if (houseNumber.isNotEmpty && road.isNotEmpty) '$houseNumber $road' else road,
      if (landmark.isNotEmpty && normalize(landmark) != normalize(road)) landmark,
      quarter,
      commune,
      city,
      text(address['country']),
    ]
        .where((part) => part.trim().isNotEmpty)
        .fold<List<String>>(<String>[], (items, part) {
      if (!items.any((existing) => normalize(existing) == normalize(part))) {
        items.add(part.trim());
      }
      return items;
    }).join(', ');

    // V69 : même référentiel que le Web. Lorsque Laravel fournit display_name,
    // l'application l'affiche tel quel au lieu de recomposer un autre libellé.
    final displayName = firstNonEmpty([
      rawDisplay,
      composedAddress,
      'Position actuelle détectée',
    ]);

    double coordinate(dynamic value, double fallback) {
      if (value is num) return value.toDouble();
      return double.tryParse('${value ?? ''}') ?? fallback;
    }

    return GeoResolvedPlace(
      displayName: displayName,
      zone: zone,
      commune: commune,
      quartier: quartier,
      city: city,
      localityType: text(resolved['locality_type']),
      landmark: landmark,
      source: firstNonEmpty([text(json['source']), text(resolved['source']), 'ovanie_backend']),
      latitude: coordinate(json['latitude'], fallbackLatitude),
      longitude: coordinate(json['longitude'], fallbackLongitude),
    );
  }

  /// Construit une adresse depuis Android Geocoder lorsqu'OVANIE/Mapbox/Nominatim
  /// ne peut pas identifier le point. Les coordonnées restent celles du GPS.
  factory GeoResolvedPlace.fromNativeAddress(
    NativeResolvedAddress native, {
    required double latitude,
    required double longitude,
  }) {
    String normalize(String value) => value
        .toLowerCase()
        .replaceAll('é', 'e')
        .replaceAll('è', 'e')
        .replaceAll('ê', 'e')
        .replaceAll('à', 'a')
        .replaceAll('â', 'a')
        .replaceAll('ô', 'o')
        .replaceAll('û', 'u')
        .replaceAll(RegExp(r'[^a-z0-9]+'), '');

    const communes = <String, String>{
      'abobo': 'Abobo',
      'adjame': 'Adjamé',
      'anyama': 'Anyama',
      'attecoube': 'Attécoubé',
      'bingerville': 'Bingerville',
      'cocody': 'Cocody',
      'koumassi': 'Koumassi',
      'marcory': 'Marcory',
      'plateau': 'Plateau',
      'portbouet': 'Port-Bouët',
      'songon': 'Songon',
      'treichville': 'Treichville',
      'yopougon': 'Yopougon',
    };

    final candidates = <String>[
      native.subLocality,
      native.locality,
      native.subAdminArea,
      native.adminArea,
      native.displayName,
    ];

    String commune = '';
    for (final candidate in candidates) {
      final candidateKey = normalize(candidate);
      for (final entry in communes.entries) {
        if (candidateKey == entry.key || candidateKey.contains(entry.key)) {
          commune = entry.value;
          break;
        }
      }
      if (commune.isNotEmpty) break;
    }

    final all = candidates.join(' ').toLowerCase();
    final inAbidjan = commune.isNotEmpty || all.contains('abidjan');
    final city = inAbidjan
        ? 'Abidjan'
        : (native.locality.isNotEmpty
            ? native.locality
            : native.subAdminArea.isNotEmpty
                ? native.subAdminArea
                : native.adminArea);

    if (commune.isEmpty) {
      commune = inAbidjan
          ? (native.locality.isNotEmpty && normalize(native.locality) != 'abidjan'
              ? native.locality
              : 'Abidjan')
          : (native.locality.isNotEmpty ? native.locality : native.subAdminArea);
    }

    String quartier = native.subLocality;
    if (normalize(quartier) == normalize(commune) ||
        normalize(quartier) == normalize(city)) {
      quartier = '';
    }
    if (quartier.isEmpty && native.featureName.isNotEmpty &&
        normalize(native.featureName) != normalize(commune) &&
        normalize(native.featureName) != normalize(city)) {
      quartier = native.featureName;
    }
    if (quartier.isEmpty && native.thoroughfare.isNotEmpty) {
      quartier = native.thoroughfare;
    }

    final display = native.displayName.isNotEmpty
        ? native.displayName
        : <String>[
            native.featureName,
            native.thoroughfare,
            quartier,
            commune,
            city,
            native.countryName,
          ].where((e) => e.trim().isNotEmpty).toSet().join(', ');

    return GeoResolvedPlace(
      displayName: display.isEmpty ? 'Position actuelle détectée' : display,
      zone: inAbidjan ? 'abidjan' : 'interieur',
      commune: commune,
      quartier: quartier,
      city: city,
      localityType: quartier.isNotEmpty ? 'quartier' : '',
      landmark: native.featureName.isNotEmpty && normalize(native.featureName) != normalize(quartier)
          ? native.featureName
          : native.thoroughfare,
      source: 'android_geocoder',
      latitude: latitude,
      longitude: longitude,
    );
  }
}

class GeoRepository {
  const GeoRepository();

  Future<List<GeoSearchResult>> search(String query) async {
    final clean = query.trim();
    if (clean.length < 4) return const [];

    final response = await ApiClient.dio.get<dynamic>(
      '${AppConfig.backendOrigin}/geo/search',
      queryParameters: <String, dynamic>{
        'q': clean,
        'country_code': 'ci',
      },
    );
    ApiClient.ensureSuccess(response);

    final body = _asMap(response.data);
    final rawResults = body['results'];
    if (rawResults is! List) return const [];

    return rawResults
        .whereType<Map>()
        .map((raw) => GeoSearchResult.fromJson(Map<String, dynamic>.from(raw)))
        .where(
          (item) => item.displayName.isNotEmpty &&
              (item.latitude.abs() > 0 || item.longitude.abs() > 0),
        )
        .take(5)
        .toList(growable: false);
  }

  /// Résout un point GPS en libellé humain avec le backend OVANIE comme
  /// référentiel UNIQUE. Le Web et l'APK utilisent donc exactement le même
  /// géocodeur et le même `display_name` pour les mêmes coordonnées.
  ///
  /// Les Android Geocoder / Nominatim directs ne sont plus utilisés dans le
  /// checkout normal : ils pouvaient donner une rue différente de celle du Web
  /// pour un point identique.
  Future<GeoResolvedPlace> reverse({
    required double latitude,
    required double longitude,
    String fallbackDisplayName = '',
    bool fresh = false,
  }) async {
    try {
      final response = await ApiClient.dio
          .get<dynamic>(
            '${AppConfig.backendOrigin}/geo/reverse',
            queryParameters: <String, dynamic>{
              'lat': latitude.toStringAsFixed(7),
              'lng': longitude.toStringAsFixed(7),
              if (fresh) 'fresh': 1,
              if (fresh) '_ts': DateTime.now().millisecondsSinceEpoch,
            },
          )
          .timeout(const Duration(seconds: 18));
      ApiClient.ensureSuccess(response);

      final body = _asMap(response.data);
      final result = body['result'];
      if (result is Map && result.isNotEmpty) {
        final place = GeoResolvedPlace.fromJson(
          Map<String, dynamic>.from(result),
          fallbackLatitude: latitude,
          fallbackLongitude: longitude,
          fallbackDisplayName: fallbackDisplayName,
        );
        final exact = _withExactCoordinates(place, latitude, longitude);
        if (exact.hasHumanReadableLocation) {
          return exact;
        }
      }
    } catch (_) {
      // Le serveur peut être momentanément indisponible ou inaccessible depuis
      // le téléphone en développement local. On garde toujours les coordonnées
      // GPS exactes et on cherche seulement un libellé humain en secours.
    }

    // Secours 1 : géocodeur Android natif. Il ne fabrique aucune coordonnée :
    // il transforme uniquement le point GPS réel reçu par le téléphone en adresse.
    try {
      final native = await DeviceLocation.reverseGeocodeNative(
        latitude: latitude,
        longitude: longitude,
      );
      if (native != null) {
        final place = GeoResolvedPlace.fromNativeAddress(
          native,
          latitude: latitude,
          longitude: longitude,
        );
        if (place.hasHumanReadableLocation) return place;
      }
    } catch (_) {}

    // Secours 2 : Nominatim, toujours avec les coordonnées GPS d'origine.
    final direct = await _reverseViaNominatimDirect(
      latitude: latitude,
      longitude: longitude,
    );
    if (direct != null && direct.hasHumanReadableLocation) return direct;

    throw const OvanieApiException(
      'Adresse non identifiée. Recherchez votre lieu ou réessayez.',
    );
  }

  Future<GeoResolvedPlace?> _reverseViaNominatimDirect({
    required double latitude,
    required double longitude,
  }) async {
    try {
      final dio = Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 4),
          receiveTimeout: const Duration(seconds: 8),
          sendTimeout: const Duration(seconds: 4),
          headers: const <String, dynamic>{
            'Accept': 'application/json',
            'Accept-Language': 'fr',
            'User-Agent': 'OVANIE-Mobile/1.0 (https://ovanie.com)',
          },
        ),
      );

      final response = await dio.get<dynamic>(
        'https://nominatim.openstreetmap.org/reverse',
        queryParameters: <String, dynamic>{
          'format': 'jsonv2',
          'lat': latitude.toStringAsFixed(7),
          'lon': longitude.toStringAsFixed(7),
          'zoom': 18,
          'addressdetails': 1,
          'namedetails': 1,
          'accept-language': 'fr',
        },
      );

      if (response.statusCode == null ||
          response.statusCode! < 200 ||
          response.statusCode! >= 300) {
        return null;
      }

      final raw = _asMap(response.data);
      if (raw.isEmpty) return null;
      final address = _asMap(raw['address']);
      final mapped = <String, dynamic>{
        'latitude': latitude,
        'longitude': longitude,
        'display_name': (raw['display_name'] ?? '').toString(),
        'address': address,
        'feature_name': (raw['name'] ?? '').toString(),
        'source': 'nominatim_mobile_fallback',
      };

      final place = GeoResolvedPlace.fromJson(
        mapped,
        fallbackLatitude: latitude,
        fallbackLongitude: longitude,
      );
      return _withExactCoordinates(place, latitude, longitude);
    } catch (_) {
      return null;
    }
  }

  GeoResolvedPlace _withExactCoordinates(
    GeoResolvedPlace place,
    double latitude,
    double longitude,
  ) {
    return GeoResolvedPlace(
      displayName: place.displayName,
      zone: place.zone,
      commune: place.commune,
      quartier: place.quartier,
      city: place.city,
      localityType: place.localityType,
      landmark: place.landmark,
      source: place.source,
      latitude: latitude,
      longitude: longitude,
    );
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) return Map<String, dynamic>.from(value);
    if (value is String) {
      try {
        final decoded = jsonDecode(value);
        if (decoded is Map) return Map<String, dynamic>.from(decoded);
      } catch (_) {}
    }
    return <String, dynamic>{};
  }
}
