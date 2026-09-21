/// Représentation minimale du livreur telle que renvoyée par l'API Laravel.
///
/// Le contrat exact des champs supplémentaires n'est pas encore figé côté
/// backend : seuls les champs listés dans la spécification sont lus, tout le
/// reste de la réponse JSON est ignoré sans faire échouer le parsing.
class DriverProfile {
  const DriverProfile({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.phone,
    this.onboardingStatus,
    this.email,
    this.vehicle,
    this.plate,
    this.zones = const [],
    this.availabilityDays = const [],
    this.avatarUrl,
  });

  final String id;
  final String firstName;
  final String lastName;
  final String phone;

  /// Valeurs attendues (à confirmer avec le backend) : "invited",
  /// "pending_review", "rejected", "active".
  final String? onboardingStatus;

  final String? email;

  /// Type de véhicule déclaré à l'inscription (moto, tricycle, pickup, ...).
  final String? vehicle;

  /// Numéro d'immatriculation, lu depuis `profile.plate`.
  final String? plate;

  /// Communes d'intervention, lues depuis `profile.zones`.
  final List<String> zones;

  /// Jours de disponibilité déclarés, lus depuis `profile.availability_days`.
  final List<String> availabilityDays;

  final String? avatarUrl;

  factory DriverProfile.fromJson(Map<String, dynamic> json) {
    final profile = json['profile'];
    final profileMap = profile is Map ? Map<String, dynamic>.from(profile) : const <String, dynamic>{};
    return DriverProfile(
      id: '${json['id'] ?? ''}',
      firstName: '${json['first_name'] ?? ''}',
      lastName: '${json['last_name'] ?? ''}',
      phone: '${json['phone'] ?? ''}',
      onboardingStatus: json['onboarding_status']?.toString(),
      email: json['email']?.toString(),
      vehicle: json['vehicle']?.toString(),
      plate: profileMap['plate']?.toString(),
      zones: (profileMap['zones'] is List)
          ? List<String>.from((profileMap['zones'] as List).map((e) => e.toString()))
          : const [],
      availabilityDays: (profileMap['availability_days'] is List)
          ? List<String>.from((profileMap['availability_days'] as List).map((e) => e.toString()))
          : const [],
      avatarUrl: json['avatar_url']?.toString(),
    );
  }

  String get fullName => '$firstName $lastName'.trim();
}
