import 'dart:io';

import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/token_storage.dart';
import '../../../core/vehicle/vehicle_color_detector.dart';
import '../../onboarding/vehicle_categories.dart';
import '../models/driver_profile.dart';
import '../models/driver_dashboard.dart';

/// Résultat de la vérification de numéro (`/driver/auth/check-phone`).
class PhoneCheckResult {
  const PhoneCheckResult({
    required this.registered,
    this.driver,
    this.onboardingStatus,
    this.canLogin = false,
  });

  final bool registered;
  final DriverProfile? driver;
  final String? onboardingStatus;
  final bool canLogin;

  factory PhoneCheckResult.fromJson(Map<String, dynamic> json) {
    final driverJson = json['driver'];
    return PhoneCheckResult(
      registered: json['registered'] == true,
      driver: driverJson is Map
          ? DriverProfile.fromJson(Map<String, dynamic>.from(driverJson))
          : null,
      onboardingStatus: json['onboarding_status']?.toString(),
      canLogin: json['can_login'] == true,
    );
  }
}

/// Dossier de véhicule collecté à l'étape 6 de l'inscription.
class VehicleFiles {
  const VehicleFiles({
    this.vehiclePhoto,
    this.platePhoto,
    this.vehicleRegistrationDocument,
    this.supportingDocuments = const [],
  });

  final File? vehiclePhoto;
  final File? platePhoto;

  /// Carte grise du véhicule (`vehicle_registration_document` côté backend).
  final File? vehicleRegistrationDocument;
  final List<File> supportingDocuments;
}

/// Point d'accès unique à l'API "driver" du backend Laravel.
///
/// Isolé du reste de l'application afin qu'un écart avec le contrat réel
/// (chemins, noms de champs, format de réponse) ne nécessite de corriger que
/// cette classe.
class DriverRepository {
  DriverRepository._();

  static final DriverRepository instance = DriverRepository._();

  Future<PhoneCheckResult> checkPhone(String phone) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/auth/check-phone',
      data: {'phone': phone},
    );
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      return PhoneCheckResult.fromJson(Map<String, dynamic>.from(data));
    }
    return const PhoneCheckResult(registered: false);
  }

  /// Déclenche l'envoi d'un code OTP par SMS au numéro donné.
  ///
  /// Contrat backend (`POST /driver/auth/login`) : reçoit `{"phone": "..."}`
  /// et répond `{"otp_sent": true, "expires_in": 600}`. Le livreur n'a plus
  /// de code personnel à retenir : l'écran appelant doit ensuite naviguer
  /// vers [OtpScreen] et attendre [verifyOtp].
  Future<OtpSentResult> login(String phone) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/auth/login',
      data: {'phone': phone},
    );
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      final map = Map<String, dynamic>.from(data);
      final expiresIn = int.tryParse('${map['expires_in'] ?? ''}') ?? 600;
      return OtpSentResult(expiresIn: expiresIn);
    }
    throw const OvanieApiException(
      'Réponse de connexion inattendue du serveur. Veuillez réessayer.',
    );
  }

  /// Redemande un nouveau code OTP (throttlé côté backend).
  ///
  /// Contrat backend (`POST /driver/auth/login/resend`) : reçoit
  /// `{"phone": "..."}`, répond comme [login], ou 429 si l'appel est trop
  /// rapproché du précédent envoi.
  Future<OtpSentResult> resendOtp(String phone) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/auth/login/resend',
      data: {'phone': phone},
    );
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      final map = Map<String, dynamic>.from(data);
      final expiresIn = int.tryParse('${map['expires_in'] ?? ''}') ?? 600;
      return OtpSentResult(expiresIn: expiresIn);
    }
    throw const OvanieApiException(
      'Réponse de connexion inattendue du serveur. Veuillez réessayer.',
    );
  }

  /// Vérifie le code OTP reçu par SMS et ouvre la session.
  ///
  /// Contrat backend (`POST /driver/auth/login/verify`) : reçoit
  /// `{"phone": "...", "otp": "123456"}`, répond
  /// `{"token": "...", "driver": {...}}` ou une erreur 422 si le code est
  /// invalide/expiré.
  Future<LoginResult> verifyOtp({required String phone, required String otp}) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/auth/login/verify',
      data: {'phone': phone, 'otp': otp},
    );
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      final map = Map<String, dynamic>.from(data);
      final token = (map['token'] ?? map['access_token'])?.toString();
      if (token != null && token.isNotEmpty) {
        await TokenStorage.instance.writeToken(token);
        ApiClient.setBearerToken(token);
        final driverJson = map['driver'];
        return LoginResult(
          token: token,
          driver: driverJson is Map
              ? DriverProfile.fromJson(Map<String, dynamic>.from(driverJson))
              : null,
        );
      }
    }
    throw const OvanieApiException('Code de vérification invalide.');
  }

  Future<DriverProfile> me() async {
    final response = await ApiClient.dio.get<dynamic>('/driver/me');
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      return DriverProfile.fromJson(Map<String, dynamic>.from(data));
    }
    throw const OvanieApiException('Profil livreur introuvable.');
  }



  Future<DriverDashboardData> dashboard() async {
    final response = await ApiClient.dio.get<dynamic>('/driver/dashboard');
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      return DriverDashboardData.fromJson(Map<String, dynamic>.from(data));
    }
    throw const OvanieApiException('Tableau de bord livreur indisponible.');
  }

  Future<String> updateAvailability(String status) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/availability',
      data: {'status': status},
    );
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map && data['status'] != null) {
      return data['status'].toString();
    }
    return status;
  }

  Future<void> sendPresence({
    required double latitude,
    required double longitude,
    double? accuracy,
    double? speed,
    double? heading,
  }) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/driver/presence',
      data: {
        'latitude': latitude,
        'longitude': longitude,
        if (accuracy != null) 'accuracy': accuracy,
        if (speed != null && speed >= 0) 'speed': speed,
        if (heading != null && heading >= 0) 'heading': heading,
      },
    );
    ApiClient.ensureSuccess(response);
  }

  Future<void> setOffline() async {
    final response = await ApiClient.dio.post<dynamic>('/driver/presence/offline');
    ApiClient.ensureSuccess(response);
  }

  /// Liste des communes réellement ouvertes à l'inscription, décidée côté
  /// Logistique (page Territoire) : une commune désactivée, ou rattachée
  /// uniquement à des zones désactivées, n'apparaît plus ici.
  ///
  /// Contrat backend (`GET /driver/territory/communes`, auth:sanctum) :
  /// répond `{"communes": [{"id": 1, "name": "Cocody"}, ...]}` triées par nom.
  Future<List<TerritoryCommune>> fetchAvailableCommunes() async {
    final response = await ApiClient.dio.get<dynamic>('/driver/territory/communes');
    ApiClient.ensureSuccess(response);
    final data = response.data;
    if (data is Map) {
      final list = data['communes'];
      if (list is List) {
        return list
            .whereType<Map>()
            .map((item) => TerritoryCommune.fromJson(Map<String, dynamic>.from(item)))
            .toList();
      }
    }
    throw const OvanieApiException('Liste des communes indisponible.');
  }

  /// Soumet le dossier d'inscription.
  ///
  /// Contrat backend (`POST /driver/onboarding/submit`, multipart, cf.
  /// `DriverOnboardingController::submit()`) : `vehicle` (enum strict
  /// moto|tricycle|pickup|camion_3t|camion_10t), `plate`,
  /// `availability_days[]` (lundi..dimanche), `zone_ids[]` (entiers,
  /// `abidjan_communes.id`), `photo` (photo de profil, optionnelle),
  /// `vehicle_registration_document` (carte grise, optionnelle),
  /// `vehicle_photo` (photo réelle du véhicule, obligatoire),
  /// `plate_photo` (photo de la plaque, optionnelle).
  ///
  /// La couleur n'est pas demandée manuellement : le backend l'analyse à partir
  /// de `vehicle_photo`, puis synchronise photo/couleur/immatriculation dans Flotte.
  Future<void> submitOnboarding({
    File? profilePhoto,
    required List<int> zoneIds,
    required List<String> availabilities,
    required String vehicleType,
    required String plateNumber,
    required VehicleFiles vehicleFiles,
  }) async {
    // Détection silencieuse à partir de la vraie photo. Le serveur refait le
    // calcul si GD est disponible ; ces valeurs garantissent le fonctionnement
    // même si l'extension d'image PHP n'est pas activée sur le poste de test.
    final detectedColor = await VehicleColorDetector.detect(vehicleFiles.vehiclePhoto);

    final form = FormData.fromMap({
      'vehicle': vehicleTypeToBackendEnum(vehicleType),
      'plate': plateNumber,
      if (detectedColor != null) 'vehicle_color': detectedColor.label,
      if (detectedColor != null) 'vehicle_color_hex': detectedColor.hex,
      for (var i = 0; i < availabilities.length; i++)
        'availability_days[$i]': weekDayToBackendValue(availabilities[i]),
      for (var i = 0; i < zoneIds.length; i++) 'zone_ids[$i]': zoneIds[i],
      if (profilePhoto != null)
        'photo': await MultipartFile.fromFile(profilePhoto.path),
      if (vehicleFiles.vehicleRegistrationDocument != null)
        'vehicle_registration_document':
            await MultipartFile.fromFile(vehicleFiles.vehicleRegistrationDocument!.path),
      if (vehicleFiles.vehiclePhoto != null)
        'vehicle_photo': await MultipartFile.fromFile(vehicleFiles.vehiclePhoto!.path),
      if (vehicleFiles.platePhoto != null)
        'plate_photo': await MultipartFile.fromFile(vehicleFiles.platePhoto!.path),
    });

    final response = await ApiClient.dio.post<dynamic>(
      '/driver/onboarding/submit',
      data: form,
    );
    ApiClient.ensureSuccess(response);
  }
}

/// Commune d'Abidjan ouverte à l'inscription (voir [DriverRepository.fetchAvailableCommunes]).
class TerritoryCommune {
  const TerritoryCommune({required this.id, required this.name});

  final int id;
  final String name;

  factory TerritoryCommune.fromJson(Map<String, dynamic> json) {
    return TerritoryCommune(
      id: int.tryParse('${json['id']}') ?? 0,
      name: (json['name'] ?? '').toString(),
    );
  }
}

class LoginResult {
  const LoginResult({this.token, this.driver});

  final String? token;

  /// Profil renvoyé par le backend à la vérification de l'OTP : permet de
  /// savoir immédiatement si l'inscription est terminée (onboarding_status)
  /// sans appel supplémentaire à /driver/me.
  final DriverProfile? driver;
}

/// Résultat d'un envoi (ou renvoi) de code OTP.
class OtpSentResult {
  const OtpSentResult({required this.expiresIn});

  /// Durée de validité du code, en secondes.
  final int expiresIn;
}
