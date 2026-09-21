class OfficialCategoryDefinition {
  final String slug;
  final String name;
  final List<String> aliases;

  const OfficialCategoryDefinition({
    required this.slug,
    required this.name,
    this.aliases = const [],
  });
}

/// Catégories commerciales affichées sur le site OVANIE.
/// Cette liste est utilisée à la fois sur l'accueil mobile et dans le catalogue
/// afin que les deux écrans restent strictement cohérents.
const officialOvanieCategories = <OfficialCategoryDefinition>[
  OfficialCategoryDefinition(
    slug: 'materiaux-gros-oeuvre',
    name: 'Matériaux gros œuvre',
    aliases: ['materiaux-gros-oeuvres', 'gros-oeuvre'],
  ),
  OfficialCategoryDefinition(
    slug: 'materiaux-ecologiques',
    name: 'Matériaux écologiques',
    aliases: ['materiaux-ecologique'],
  ),
  OfficialCategoryDefinition(
    slug: 'outillage-equipement',
    name: 'Outillage & Équipement',
    aliases: ['outillage', 'outillage-equipements'],
  ),
  OfficialCategoryDefinition(
    slug: 'materiaux-de-finition',
    name: 'Matériaux de finition',
    aliases: ['materiaux-finition', 'finition'],
  ),
  OfficialCategoryDefinition(
    slug: 'energie-solaire',
    name: 'Énergie solaire',
    aliases: ['solaire', 'energie'],
  ),
  OfficialCategoryDefinition(
    slug: 'electricite-plomberie',
    name: 'Électricité & Plomberie',
    aliases: ['electricite', 'plomberie'],
  ),
  OfficialCategoryDefinition(
    slug: 'nos-reconditionnes',
    name: 'Nos reconditionnés',
    aliases: ['nos-reconditionnee', 'reconditionnes', 'reconditionne'],
  ),
  OfficialCategoryDefinition(
    slug: 'carte-cadeau-ovanie',
    name: 'Carte cadeau Ovanie',
    aliases: ['carte-cadeau', 'carte cadeau'],
  ),
];
