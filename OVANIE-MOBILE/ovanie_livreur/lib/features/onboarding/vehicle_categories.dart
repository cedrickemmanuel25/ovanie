import '../../core/reference_data/reference_data_store.dart';

/// Convertit un libellé ou un code véhicule renvoyé par Laravel vers le code
/// canonique attendu par le backend. Aucune table locale de secours.
String vehicleTypeToBackendEnum(String label) {
  final normalized = label.trim().toLowerCase();
  for (final option
      in OvanieReferenceDataStore.instance.options('vehicle_types')) {
    if (option.label.trim().toLowerCase() == normalized ||
        option.code.trim().toLowerCase() == normalized) {
      return option.code;
    }
  }
  throw ArgumentError(
    'Type de véhicule absent du référentiel Laravel : "$label".',
  );
}

/// Convertit un libellé ou un code de jour renvoyé par Laravel vers le code
/// canonique attendu par le backend. Aucune table locale de secours.
String weekDayToBackendValue(String label) {
  final normalized = label.trim().toLowerCase();
  for (final option in OvanieReferenceDataStore.instance.options('week_days')) {
    if (option.label.trim().toLowerCase() == normalized ||
        option.code.trim().toLowerCase() == normalized) {
      return option.code;
    }
  }
  throw ArgumentError(
    'Jour absent du référentiel Laravel : "$label".',
  );
}
