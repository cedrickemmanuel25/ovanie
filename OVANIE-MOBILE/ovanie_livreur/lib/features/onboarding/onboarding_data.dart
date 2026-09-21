import 'dart:io';

import '../../core/reference_data/reference_data_store.dart';
import '../driver/data/driver_repository.dart';
import '../driver/models/driver_profile.dart';

/// Référentiels d'onboarding chargés exclusivement depuis Laravel.
///
/// Étape 7 : aucune commune, aucun jour et aucun véhicule ne sont codés
/// localement dans Flutter. Si reference-data est indisponible, les écrans
/// reçoivent une liste vide et doivent demander un rechargement.
List<String> get kAbidjanCommunes =>
    OvanieReferenceDataStore.instance.communeNames;

List<String> get kWeekDays => OvanieReferenceDataStore.instance
    .options('week_days')
    .map((item) => item.label)
    .where((value) => value.isNotEmpty)
    .toList(growable: false);

List<String> get kVehicleTypes => OvanieReferenceDataStore.instance
    .options('vehicle_types')
    .map((item) => item.label)
    .where((value) => value.isNotEmpty)
    .toList(growable: false);

/// État accumulé au fil du tunnel d'inscription du livreur.
///
/// Un simple objet mutable transmis d'écran en écran : cohérent avec le
/// setState/Navigator classique déjà utilisé par les autres applications
/// OVANIE (pas de gestionnaire d'état tiers introduit ici).
class OnboardingData {
  String phone = '';
  String firstName = '';
  String lastName = '';
  String? onboardingStatus;

  File? profilePhoto;

  /// Communes sélectionnées, indexées par id (contrat backend : `zone_ids[]`
  /// attend des entiers, cf. `abidjan_communes.id`). On garde id ET nom pour
  /// pouvoir afficher les libellés (zones_screen.dart, confirmation_screen.dart)
  /// sans rappeler l'API.
  final Map<int, String> zones = {};
  final Set<String> availabilities = {};

  String? vehicleType;
  String plateNumber = '';
  File? vehiclePhoto;
  File? platePhoto;
  File? vehicleRegistrationDocument;
  final List<File> supportingDocuments = [];

  bool hasZone(TerritoryCommune commune) => zones.containsKey(commune.id);

  void toggleZone(TerritoryCommune commune) {
    if (zones.containsKey(commune.id)) {
      zones.remove(commune.id);
    } else {
      zones[commune.id] = commune.name;
    }
  }

  void applyDriver(DriverProfile driver) {
    phone = driver.phone;
    firstName = driver.firstName;
    lastName = driver.lastName;
    onboardingStatus = driver.onboardingStatus;
  }
}
