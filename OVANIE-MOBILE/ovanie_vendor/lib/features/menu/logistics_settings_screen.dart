import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../../core/network/api_client.dart';
import 'delivery_screens.dart';
import 'menu_ui.dart';

class LogisticsSettingsScreen extends StatefulWidget {
  const LogisticsSettingsScreen({
    super.key,
    required this.shop,
    required this.targetMode,
  });

  final Map<String, dynamic> shop;
  final String targetMode;

  @override
  State<LogisticsSettingsScreen> createState() => _LogisticsSettingsState();
}

class _LogisticsSettingsState extends State<LogisticsSettingsScreen> {
  final form = GlobalKey<FormState>();
  final fields = <String, TextEditingController>{};
  Map<String, dynamic> profile = {};
  List<Map<String, dynamic>> zones = [];
  bool loading = true,
      saving = false,
      locating = false,
      locationConfirmed = false;
  String? error;
  bool locationReady = false;
  double? gpsAccuracy;
  String? gpsCapturedAt;
  String? locationToken;
  Map<String, dynamic> detectedLocation = {};
  String gpsFeedback =
      'Placez-vous à la boutique avec votre téléphone, puis lancez la localisation.';

  bool get seller => widget.targetMode == 'seller';
  bool get changing =>
      widget.targetMode != screenText(widget.shop['logistics_type'], 'ovanie');
  String get label => seller ? 'Ma propre logistique' : 'OVANIE Logistics';
  bool get zonesReady =>
      zones.any((z) => z['is_active'] == true) &&
      zones
          .where((z) => z['is_active'] == true)
          .every(
            (z) =>
                screenText(z['commune'], '').trim().isNotEmpty &&
                z['delivery_price'] != null &&
                number(z['delivery_price']) >= 0 &&
                screenText(z['estimated_delay'], '').trim().isNotEmpty,
          );

  @override
  void initState() {
    super.initState();
    locationReady =
        !changing && widget.shop['logistics_location_ready'] == true;
    if (locationReady) {
      gpsFeedback = 'La localisation précédemment enregistrée est disponible.';
    }
    for (final key in [
      'address',
      'commune',
      'district',
      'landmark',
      'latitude',
      'longitude',
      'default_delay',
      'max_weight_kg',
      'max_volume_m3',
      'conditions',
    ]) {
      fields[key] = TextEditingController(
        text: screenText(widget.shop[key], ''),
      );
    }
    load();
  }

  @override
  void dispose() {
    for (final field in fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  Future<void> load() async {
    if (!seller) {
      setState(() => loading = false);
      return;
    }
    try {
      final data = await menuApi('shop/delivery?configure=1');
      if (!mounted) return;
      profile = mapOf(data['profile']);
      zones = rowsOf(data['zones']);
      for (final key in [
        'default_delay',
        'max_weight_kg',
        'max_volume_m3',
        'conditions',
      ]) {
        fields[key]!.text = screenText(profile[key], '');
      }
      error = null;
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> editZones() async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => const DeliveryZonesScreen(configuring: true),
      ),
    );
    if (!mounted) return;
    try {
      final data = await menuApi('shop/delivery?configure=1');
      if (mounted) setState(() => zones = rowsOf(data['zones']));
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  Future<void> captureGps() async {
    setState(() {
      locating = true;
      locationReady = false;
      locationConfirmed = false;
      gpsAccuracy = null;
      gpsCapturedAt = null;
      locationToken = null;
      detectedLocation = {};
      for (final key in [
        'address',
        'commune',
        'district',
        'landmark',
        'latitude',
        'longitude',
      ]) {
        fields[key]!.clear();
      }
      gpsFeedback = 'Recherche d’une position suffisamment précise…';
    });
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw Exception(
          'Activez la localisation et placez-vous à la boutique.',
        );
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        throw Exception(
          'Autorisez la localisation précise, puis réessayez depuis la boutique.',
        );
      }
      final position =
          await Geolocator.getPositionStream(
                locationSettings: const LocationSettings(
                  accuracy: LocationAccuracy.best,
                  distanceFilter: 0,
                ),
              )
              .where((position) {
                final age = DateTime.now()
                    .difference(position.timestamp)
                    .inMilliseconds;
                return position.accuracy.isFinite &&
                    position.accuracy > 0 &&
                    position.accuracy <= 50 &&
                    age >= -10000 &&
                    age <= 30000 &&
                    !position.isMocked &&
                    position.latitude.isFinite &&
                    position.longitude.isFinite;
              })
              .timeout(const Duration(seconds: 45))
              .first;
      if (!mounted) return;
      final age = DateTime.now().difference(position.timestamp).inMilliseconds;
      if (!position.accuracy.isFinite ||
          position.accuracy <= 0 ||
          position.accuracy > 50 ||
          age < -10000 ||
          age > 30000 ||
          position.isMocked ||
          !position.latitude.isFinite ||
          !position.longitude.isFinite) {
        throw Exception(
          'La position reçue est trop imprécise ou non fiable. Réessayez depuis la boutique avec la localisation précise activée.',
        );
      }
      fields['latitude']!.text = position.latitude.toStringAsFixed(7);
      fields['longitude']!.text = position.longitude.toStringAsFixed(7);
      final capturedAt = position.timestamp.toUtc().toIso8601String();
      setState(
        () => gpsFeedback =
            'Position reçue. Identification de son adresse actuelle…',
      );
      final resolved = await menuApi(
        'shop/logistics-location',
        method: 'POST',
        data: {
          'latitude': fields['latitude']!.text,
          'longitude': fields['longitude']!.text,
          'geo_accuracy': position.accuracy,
          'geo_captured_at': capturedAt,
        },
      );
      if (!mounted) return;
      if (screenText(resolved['location_token'], '').isEmpty ||
          screenText(resolved['commune'], '').isEmpty ||
          screenText(resolved['address'], '').isEmpty) {
        throw Exception(
          'L’adresse de la position ne peut pas être identifiée. Réessayez.',
        );
      }
      for (final key in ['address', 'commune', 'district', 'landmark']) {
        fields[key]!.text = screenText(resolved[key], '');
      }
      setState(() {
        detectedLocation = resolved;
        locationToken = resolved['location_token'];
        locationReady = true;
        gpsAccuracy = position.accuracy;
        gpsCapturedAt = capturedAt;
        gpsFeedback =
            'Adresse détectée pour cette position. Vérifiez le point sur la carte et complétez les détails manquants avant de confirmer.';
      });
    } catch (e) {
      if (mounted) {
        setState(
          () => gpsFeedback =
              'Localisation non validée. Réessayez depuis la boutique.',
        );
        menuError(context, e);
      }
    } finally {
      if (mounted) setState(() => locating = false);
    }
  }

  Future<void> save({bool activate = false}) async {
    if (!form.currentState!.validate()) return;
    if (!seller && (!locationReady || !locationConfirmed)) {
      menuError(
        context,
        Exception('Confirmez le point d’enlèvement et la position GPS.'),
      );
      return;
    }
    if (activate && !zonesReady && seller) {
      menuError(
        context,
        Exception(
          'Complétez au moins une zone active avec un tarif et un délai.',
        ),
      );
      return;
    }
    if (activate && changing) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Changer de mode logistique ?'),
          content: Text(
            'Activer $label pour les nouvelles commandes ? Les commandes déjà créées ou en cours conserveront leur mode logistique.${seller ? '' : ' Vos zones, tarifs et délais seront conservés mais désactivés.'}',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Annuler'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Confirmer'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }
    setState(() => saving = true);
    try {
      if (seller) {
        await menuApi(
          'shop/delivery',
          method: 'POST',
          data: {
            for (final key in [
              'default_delay',
              'max_weight_kg',
              'max_volume_m3',
              'conditions',
            ])
              key: fields[key]!.text.trim(),
            'vehicle_types': profile['vehicle_types'] ?? [],
            'capacity_description': profile['capacity_description'],
          },
        );
      }
      if (!seller || activate) {
        await menuApi(
          'shop/logistics-settings',
          method: 'POST',
          data: {
            'logistics_type': widget.targetMode,
            'expected_logistics_type': screenText(
              widget.shop['logistics_type'],
              'ovanie',
            ),
            'confirmed': activate && changing,
            if (!seller) ...{
              for (final key in [
                'address',
                'commune',
                'district',
                'landmark',
                'latitude',
                'longitude',
              ])
                key: fields[key]!.text.trim(),
              'location_confirmed': locationConfirmed,
              if (gpsCapturedAt != null) ...{
                'geo_source': 'browser_gps',
                'geo_accuracy': gpsAccuracy,
                'geo_captured_at': gpsCapturedAt,
                'location_token': locationToken,
              },
            },
          },
        );
        if (mounted) Navigator.pop(context, true);
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Informations enregistrées.')),
        );
      }
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Widget field(
    String key,
    String title, {
    bool numeric = false,
    double? limit,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: TextFormField(
      controller: fields[key],
      enabled: !saving && !locating,
      readOnly:
          locationToken != null &&
          ['address', 'commune', 'district'].contains(key) &&
          screenText(detectedLocation[key], '').isNotEmpty,
      keyboardType: numeric
          ? const TextInputType.numberWithOptions(decimal: true, signed: true)
          : TextInputType.text,
      decoration: InputDecoration(
        labelText: '$title *',
        border: const OutlineInputBorder(),
      ),
      onChanged: seller
          ? null
          : (_) => setState(() {
              locationConfirmed = false;
              if (locationToken != null &&
                  ['address', 'landmark', 'district'].contains(key)) {
                return;
              }
              locationReady = false;
              gpsAccuracy = null;
              gpsCapturedAt = null;
              locationToken = null;
              detectedLocation = {};
              fields['latitude']!.clear();
              fields['longitude']!.clear();
              gpsFeedback =
                  'Adresse modifiée. Localisez à nouveau la boutique.';
            }),
      validator: (value) {
        if (value == null || value.trim().isEmpty) return 'Champ obligatoire';
        if (numeric) {
          final n = double.tryParse(value.trim());
          if (n == null ||
              !n.isFinite ||
              (limit == null ? n <= 0 : n.abs() > limit)) {
            return 'Valeur invalide';
          }
        }
        return null;
      },
    ),
  );

  @override
  Widget build(BuildContext context) => MenuPage(
    title: changing ? 'Passer à $label' : 'Informations logistiques',
    subtitle: 'Livraison & logistique',
    loading: loading,
    error: error,
    refresh: load,
    children: [
      ShopIdentity(shop: widget.shop),
      if (changing)
        menuInfo(
          'Le mode actuel reste actif jusqu’à votre confirmation. Les commandes existantes ne changent pas.',
        ),
      Form(
        key: form,
        child: DataCard(
          title: seller ? 'Capacités et délais' : 'Point d’enlèvement',
          child: Column(
            children: [
              if (seller) ...[
                field('default_delay', 'Délai de préparation avant départ'),
                field('max_weight_kg', 'Poids maximum (kg)', numeric: true),
                field('max_volume_m3', 'Volume maximum (m³)', numeric: true),
                field('conditions', 'Conditions de livraison'),
              ] else ...[
                menuInfo(
                  locationToken == null
                      ? 'Informations enregistrées, non issues d’une nouvelle capture. Localisez la boutique sur place pour détecter son adresse actuelle.'
                      : 'Informations issues de la nouvelle position. Aucune adresse d’ouverture n’a été réutilisée.',
                ),
                field('address', 'Adresse de la boutique'),
                field('commune', 'Commune'),
                field('district', 'Quartier'),
                field('landmark', 'Point de repère'),
                menuButton(
                  locating ? 'Localisation…' : 'Utiliser ma position GPS',
                  locating || saving ? null : captureGps,
                  icon: Icons.my_location,
                ),
                if (locationToken != null) ...[
                  Text(
                    screenText(
                      detectedLocation['display_name'],
                      fields['address']!.text,
                    ),
                  ),
                  TextButton.icon(
                    icon: const Icon(Icons.map_outlined),
                    label: const Text('Vérifier le point sur la carte'),
                    onPressed: () async {
                      try {
                        final lat = fields['latitude']!.text,
                            lng = fields['longitude']!.text;
                        await launchUrl(
                          Uri.parse(
                            'https://www.openstreetmap.org/?mlat=$lat&mlon=$lng#map=19/$lat/$lng',
                          ),
                        );
                      } catch (e) {
                        if (context.mounted) menuError(context, e);
                      }
                    },
                  ),
                ],
                CheckboxListTile(
                  value: locationConfirmed,
                  contentPadding: EdgeInsets.zero,
                  title: const Text(
                    'Je confirme que l’adresse est correcte et que la localisation a été effectuée au point d’enlèvement de ma boutique.',
                  ),
                  onChanged: saving || locating || !locationReady
                      ? null
                      : (value) =>
                            setState(() => locationConfirmed = value == true),
                ),
                Text(gpsFeedback, style: const TextStyle(color: menuMuted)),
              ],
            ],
          ),
        ),
      ),
      if (seller) ...[
        menuButton(
          'Enregistrer mes informations logistiques',
          saving ? null : () => save(),
        ),
        DataCard(
          title: 'Zones, tarifs et délais',
          child: ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(
              zonesReady
                  ? 'Zones de livraison configurées'
                  : 'Compléter mes zones de livraison',
            ),
            subtitle: const Text(
              'Au moins une zone active avec un tarif et un délai est obligatoire.',
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: saving ? null : editZones,
          ),
        ),
      ],
      if (changing || !seller)
        menuButton(
          saving
              ? 'Enregistrement…'
              : changing
              ? 'Activer $label'
              : 'Enregistrer mes informations',
          saving ||
                  locating ||
                  (seller && !zonesReady) ||
                  (!seller && !locationReady)
              ? null
              : () => save(activate: changing),
        ),
    ],
  );
}
