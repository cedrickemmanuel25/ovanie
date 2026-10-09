import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import '../../core/network/api_client.dart';
import '../orders/order_widgets.dart' show OrderMapImage, OrderCheckLine;
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
  String get currentMode => widget.shop['logistics_configured'] == true
      ? screenText(widget.shop['logistics_type'], '')
      : '';
  bool get changing => currentMode.isNotEmpty && widget.targetMode != currentMode;
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
        builder: (dialog) => MkTheme(
          lightStatusBar: false,
          child: Dialog(
            backgroundColor: Colors.white,
            insetPadding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 22, 18, 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Changer de mode logistique ?',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: menuBlue, fontSize: 19, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Activer $label pour les nouvelles commandes ? Les commandes déjà créées ou en cours conserveront leur mode logistique.${seller ? '' : ' Vos zones, tarifs et délais seront conservés mais désactivés.'}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: menuMuted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Expanded(
                        child: TextButton(
                          onPressed: () => Navigator.pop(dialog, false),
                          child: const Text('Annuler', style: TextStyle(color: menuBlue, fontSize: 15, fontWeight: FontWeight.w600)),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: MkBarButton(label: 'Confirmer', filled: true, onTap: () => Navigator.pop(dialog, true)),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
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
              key: key == 'default_delay' || key == 'conditions'
                  ? fields[key]!.text.trim()
                  : fields[key]!.text.trim().replaceAll(',', '.'),
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
            'expected_logistics_type': currentMode.isEmpty ? null : currentMode,
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

  Widget field(String key, String title, {bool numeric = false, double? limit, String? hint, IconData? icon}) {
    final detected =
        locationToken != null &&
        ['address', 'commune', 'district'].contains(key) &&
        screenText(detectedLocation[key], '').isNotEmpty;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          MkLabel(title, required: true),
          const SizedBox(height: 6),
          TextFormField(
            controller: fields[key],
            enabled: !saving && !locating,
            readOnly: detected,
            keyboardType: numeric ? const TextInputType.numberWithOptions(decimal: true) : TextInputType.text,
            inputFormatters: numeric
                ? [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')), LengthLimitingTextInputFormatter(9)]
                : [LengthLimitingTextInputFormatter(190)],
            style: mkFieldText,
            decoration: mkInput(hint: hint ?? title, icon: icon, readOnly: detected),
            onChanged: seller
                ? null
                : (_) => setState(() {
                    locationConfirmed = false;
                    if (locationToken != null && ['address', 'landmark', 'district'].contains(key)) {
                      return;
                    }
                    locationReady = false;
                    gpsAccuracy = null;
                    gpsCapturedAt = null;
                    locationToken = null;
                    detectedLocation = {};
                    fields['latitude']!.clear();
                    fields['longitude']!.clear();
                    gpsFeedback = 'Adresse modifiée. Localisez à nouveau la boutique.';
                  }),
            validator: (value) {
              if (value == null || value.trim().isEmpty) return 'Champ obligatoire';
              if (numeric) {
                final n = double.tryParse(value.trim().replaceAll(',', '.'));
                if (n == null || !n.isFinite || (limit == null ? n <= 0 : n.abs() > limit)) {
                  return 'Valeur invalide';
                }
              }
              return null;
            },
          ),
        ],
      ),
    );
  }

  Future<void> openMap() async {
    try {
      final lat = fields['latitude']!.text, lng = fields['longitude']!.text;
      await launchUrl(Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng'));
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  bool get _activating => changing || currentMode.isEmpty;

  @override
  Widget build(BuildContext context) {
    final blocked = saving || locating || (seller && _activating && !zonesReady) || (!seller && !locationReady);
    return MenuPage(
      title: changing ? 'Passer à $label' : 'Informations logistiques',
      subtitle: 'Livraison & logistique',
      loading: loading,
      error: error,
      refresh: load,
      actions: [
        MkBarButton(
          label: currentMode.isEmpty
              ? 'Confirmer $label'
              : changing
              ? 'Confirmer $label'
              : 'Enregistrer mes informations',
          filled: true,
          loading: saving,
          onTap: blocked ? null : () => save(activate: _activating),
        ),
      ],
      children: [
        if (changing)
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: const Color(0xFFFFF4D6), borderRadius: BorderRadius.circular(12)),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline_rounded, color: Color(0xFFB36B00), size: 22),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Le mode actuel reste actif jusqu’à votre confirmation. Les commandes existantes ne changent pas.',
                    style: TextStyle(color: Color(0xFF7A4500), fontSize: 13, height: 1.4, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
        Form(key: form, child: seller ? _capacityCard() : _pickupCard()),
        if (seller) _zonesCard(),
      ],
    );
  }

  // ---- Ma propre logistique : capacités et délais --------------------------------
  Widget _capacityCard() => DataCard(
    title: 'Capacités et délais',
    icon: Icons.local_shipping_outlined,
    iconColor: menuOrange,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (MediaQuery.sizeOf(context).width >= 340)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Image.asset('assets/images/mockups/logi_pickup.jpg', height: 120, excludeFromSemantics: true),
          ),
        field('default_delay', 'Délai de préparation avant départ', hint: 'Ex. 24 h', icon: Icons.schedule_rounded),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: field('max_weight_kg', 'Poids maximum (kg)', numeric: true, hint: 'Ex. 1500')),
            const SizedBox(width: 8),
            Expanded(child: field('max_volume_m3', 'Volume maximum (m³)', numeric: true, hint: 'Ex. 4')),
          ],
        ),
        field('conditions', 'Conditions de livraison', hint: 'Ex. Livraison au pied du chantier'),
      ],
    ),
  );

  Widget _zonesCard() => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: saving ? null : editZones,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              menuIcon(Icons.local_shipping_outlined, size: 42),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    menuHeading('Zones, tarifs et délais'),
                    const SizedBox(height: 4),
                    menuPill(
                      zonesReady ? 'Zones de livraison configurées' : 'Zones de livraison à compléter',
                      color: zonesReady ? menuGreen : const Color(0xFFE85400),
                      icon: zonesReady ? Icons.check_circle_rounded : Icons.error_outline_rounded,
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Au moins une zone active avec un tarif et un délai est obligatoire.',
                      style: TextStyle(color: menuMuted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
            ],
          ),
        ),
      ),
    ),
  );

  // ---- OVANIE Logistics : point d'enlèvement ----------------------------------------
  Widget _pickupCard() {
    final mapUrl = screenText(detectedLocation['map_url'], locationToken == null ? screenText(widget.shop['pickup_map_url'], '') : '');
    final hasPoint = fields['latitude']!.text.isNotEmpty && fields['longitude']!.text.isNotEmpty;
    return DataCard(
      title: 'Point d’enlèvement',
      icon: Icons.location_on_outlined,
      iconColor: menuOrange,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Définissez l’adresse de votre boutique pour les retraits et les livraisons.',
            style: TextStyle(color: menuMuted, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: AspectRatio(aspectRatio: 16 / 9, child: OrderMapImage(url: mapUrl)),
          ),
          const SizedBox(height: 10),
          MkBarButton(
            label: locating ? 'Localisation en cours…' : 'Utiliser ma position GPS',
            icon: Icons.my_location_rounded,
            filled: true,
            color: Mk.navy,
            loading: locating,
            onTap: locating || saving ? null : captureGps,
          ),
          const SizedBox(height: 8),
          if (locationToken != null)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFE9F7EE), borderRadius: BorderRadius.circular(12)),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.check_circle_rounded, color: menuGreen, size: 22),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      gpsFeedback,
                      style: const TextStyle(color: Color(0xFF0B5E32), fontSize: 13, height: 1.4, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            )
          else
            Text(
              gpsFeedback,
              style: const TextStyle(color: menuMuted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
            ),
          const SizedBox(height: 14),
          field('address', 'Adresse de la boutique', hint: 'Rue, immeuble, carrefour'),
          field('commune', 'Commune', hint: 'Ex. Cocody'),
          field('district', 'Quartier', hint: 'Ex. Angré 8e tranche'),
          field('landmark', 'Point de repère', hint: 'Ex. En face de la pharmacie'),
          MkBarButton(
            label: 'Vérifier le point sur la carte',
            icon: Icons.map_outlined,
            color: orderBlue,
            onTap: hasPoint ? openMap : null,
          ),
          const SizedBox(height: 8),
          OrderCheckLine(
            label:
                'Je confirme que l’adresse est correcte et que la localisation a été effectuée au point d’enlèvement de ma boutique.',
            value: locationConfirmed,
            onChanged: saving || locating || !locationReady ? null : (value) => setState(() => locationConfirmed = value),
          ),
          if (!locationReady)
            const Padding(
              padding: EdgeInsets.only(top: 4),
              child: Text(
                'Localisez d’abord la boutique avec le bouton GPS pour pouvoir confirmer.',
                style: TextStyle(color: menuMuted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w500),
              ),
            ),
        ],
      ),
    );
  }
}
