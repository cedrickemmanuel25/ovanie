import '../config/app_config.dart';

List<String> categoryWebImageCandidates(String slug) {
  final value = slug.toLowerCase().trim();

  String? optimized;
  String? legacy;

  if (value.contains('materiaux-gros-oeuvre') ||
      (value.contains('materiaux') && value.contains('gros'))) {
    optimized = 'home-gros-oeuvre.webp';
    legacy = 'gros œuvre & maçonnerie.png';
  } else if (value.contains('materiaux-ecologique') || value.contains('ecologique')) {
    // Le site n'a pas encore de visuel "home-ecologiques" historique.
    // On tente d'abord l'image définie dans la catégorie API, puis ces noms
    // conventionnels si l'administrateur ajoute le fichier plus tard.
    optimized = 'home-ecologiques.webp';
    legacy = 'Matériaux écologiques.png';
  } else if (value.contains('materiaux-de-finition') || value.contains('finition')) {
    optimized = 'home-finition.webp';
    legacy = 'finition & déco.png';
  } else if (value.contains('equipements-de-chantier') ||
      (value.contains('equipement') && value.contains('chantier'))) {
    optimized = 'home-equipement.webp';
    legacy = 'equipement de chantier.png';
  } else if (value.contains('outillage-equipement') || value.contains('outillage')) {
    optimized = 'home-outillage.webp';
    legacy = 'matériel outillage.png';
  } else if (value.contains('energie-solaire') ||
      (value.contains('energie') && value.contains('solaire'))) {
    optimized = 'home-energie.webp';
    legacy = 'énergie & autonomie.png';
  } else if (value.contains('electricite-plomberie') ||
      value.contains('plomberie') ||
      value.contains('electricite')) {
    optimized = 'home-plomberie.webp';
    legacy = 'Électricité & Plomberie.png';
  } else if (value.contains('carte-cadeau')) {
    optimized = 'home-carte-cadeau.webp';
    legacy = 'Carte Cadeau OVANIE.png';
  } else if (value.contains('recondition')) {
    optimized = 'home-reconditionnes.webp';
    legacy = 'Nos reconditionnés.png';
  }

  if (optimized == null) return const [];

  final urls = <String>[
    AppConfig.normalizeMediaUrl('/storage/logos/$optimized'),
  ];

  if (legacy != null) {
    final encoded = Uri.encodeComponent(legacy);
    urls.add(AppConfig.normalizeMediaUrl('/logos/logos/$encoded'));
    urls.add(AppConfig.normalizeMediaUrl('/logos/$encoded'));
  }

  return urls.where((url) => url.isNotEmpty).toSet().toList();
}
