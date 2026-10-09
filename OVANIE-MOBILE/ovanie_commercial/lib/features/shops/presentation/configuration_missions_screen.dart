import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/navigation/commercial_tab_bus.dart';
import '../../../core/widgets/brand_background.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../clients/presentation/client_chrome.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../../products/data/products_service.dart';
import '../../products/models/product_data.dart';
import '../../products/presentation/products_screen.dart';
import '../data/shops_service.dart';
import '../models/configuration_mission.dart';
import '../models/shop_data.dart';

const _missionBg = Color(0xFF001B43);
const _missionCard = Color(0xFFFFFFFF);
const _missionLine = Color(0xFFD8E4F2);
const _missionText = Color(0xFF09204C);
const _missionMuted = Color(0xFF6E819E);
const _missionSoftBlue = Color(0xFFF2F7FF);
const _missionGreen = Color(0xFF0A8F55);
const _missionGreenBg = Color(0xFFEAF8F1);
const _missionOrangeBg = Color(0xFFFFF2E7);

String _missionStatus(String value) => switch (value) {
      'in_progress' => 'En cours',
      'completed' => 'Terminée',
      'cancelled' => 'Annulée',
      _ => 'À démarrer',
    };

String _location(MissionShop shop) {
  final parts = <String>[shop.commune, shop.district, shop.city]
      .map((e) => e.trim())
      .where((e) => e.isNotEmpty)
      .toSet()
      .toList();
  return parts.isEmpty ? 'Localisation à vérifier' : parts.join(' · ');
}

class CommercialConfigurationMissionsScreen extends StatefulWidget {
  const CommercialConfigurationMissionsScreen({super.key, required this.shopsService});
  final ShopsService shopsService;

  @override
  State<CommercialConfigurationMissionsScreen> createState() => _CommercialConfigurationMissionsScreenState();
}

class _CommercialConfigurationMissionsScreenState extends State<CommercialConfigurationMissionsScreen> {
  ShopConfigurationMissionData? data;
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await widget.shopsService.configurationMissions();
      if (mounted) setState(() => data = result);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    } catch (_) {
      if (mounted) setState(() => error = 'Impossible de charger les missions boutiques.');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _open(ShopConfigurationMission mission) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CommercialConfigurationMissionDetailScreen(
          shopsService: widget.shopsService,
          missionId: mission.id,
        ),
      ),
    );
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    final rows = data?.missions ?? const <ShopConfigurationMission>[];
    final inProgress = rows.where((m) => m.status == 'in_progress').length;
    final pending = rows.where((m) => m.status == 'pending').length;

    return _MissionShell(
      title: 'Missions boutiques',
      onBack: () => Navigator.maybePop(context),
      child: RefreshIndicator(
        onRefresh: _load,
        color: OvanieColors.orange,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 110),
          children: [
            Row(
              children: [
                Expanded(child: _SummaryBox(value: '${rows.length}', label: 'Missions')),
                const SizedBox(width: 10),
                Expanded(child: _SummaryBox(value: '$pending', label: 'À démarrer')),
                const SizedBox(width: 10),
                Expanded(child: _SummaryBox(value: '$inProgress', label: 'En cours')),
              ],
            ),
            const SizedBox(height: 16),
            if (loading && data == null)
              const _LoadingCard()
            else if (error != null)
              _MissionErrorCard(message: error!, onRetry: _load)
            else if (rows.isEmpty)
              const _EmptyMissionCard()
            else
              ...rows.map(
                (mission) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _MissionCard(mission: mission, onTap: () => _open(mission)),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class CommercialConfigurationMissionDetailScreen extends StatefulWidget {
  const CommercialConfigurationMissionDetailScreen({
    super.key,
    required this.shopsService,
    required this.missionId,
  });

  final ShopsService shopsService;
  final int missionId;

  @override
  State<CommercialConfigurationMissionDetailScreen> createState() => _CommercialConfigurationMissionDetailScreenState();
}

class _CommercialConfigurationMissionDetailScreenState extends State<CommercialConfigurationMissionDetailScreen> {
  ShopConfigurationMission? mission;
  List<CommuneOption> communes = const [];
  bool loading = true;
  bool busy = false;
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final json = await widget.shopsService.configurationMission(widget.missionId);
      final m = ShopConfigurationMission.fromJson(Map<String, dynamic>.from(json['mission'] as Map));
      final c = (json['communes'] as List? ?? const [])
          .whereType<Map>()
          .map((e) => CommuneOption.fromJson(Map<String, dynamic>.from(e)))
          .toList();
      if (mounted) {
        setState(() {
          mission = m;
          communes = c;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    } catch (_) {
      if (mounted) setState(() => error = 'Impossible de charger cette mission.');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _run(Future<void> Function() action) async {
    if (busy) return;
    setState(() => busy = true);
    try {
      await action();
      await _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), behavior: SnackBarBehavior.floating),
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _start() => _run(() async {
        await widget.shopsService.startConfigurationMission(widget.missionId);
      });

  Future<void> _logistics() async {
    final current = mission;
    if (current == null) return;
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => _MissionLogisticsForm(
          shopsService: widget.shopsService,
          mission: current,
          communes: communes,
        ),
      ),
    );
    if (result != null) {
      await _run(() async {
        await widget.shopsService.saveMissionLogistics(widget.missionId, result);
      });
    }
  }

  Future<void> _payout() async {
    final current = mission;
    if (current == null) return;
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => _MissionPayoutForm(mission: current)),
    );
    if (result != null) {
      await _run(() async {
        await widget.shopsService.saveMissionPayout(widget.missionId, result);
      });
    }
  }

  Future<void> _productsDone() => _run(() async {
        await widget.shopsService.markMissionProductsDone(widget.missionId);
      });

  Future<void> _complete() => _run(() async {
        await widget.shopsService.completeConfigurationMission(widget.missionId);
        if (mounted) Navigator.pop(context, true);
      });

  Future<void> _products() async {
    final current = mission;
    if (current == null || !current.manageProducts || current.productsCompleted) return;

    final service = ProductsService(widget.shopsService.api, missionId: current.id);
    final shop = CommercialShopOption(
      id: current.shop.id,
      name: current.shop.name,
      category: 'Mission boutique',
      location: _location(current.shop),
      productsCount: current.shop.productsCount,
      status: 'online',
      logistics: current.shop.logisticsLabel,
    );

    Widget missionChrome({required Widget child, required int tab}) => ProductScaffold(
          child: child,
          tab: 3,
          initialUser: const {'name': 'Commercial OVANIE', 'first_name': 'commercial'},
          onTab: (index) {
            if (index == 3) return;
            CommercialTabBus.request(index);
          },
        );

    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SessionsScreen(service: service, shop: shop, chrome: missionChrome),
      ),
    );

    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final m = mission;

    return _MissionShell(
      title: 'Mission boutique',
      onBack: () => Navigator.maybePop(context),
      child: loading && m == null
          ? const _LoadingCard()
          : error != null && m == null
              ? Padding(
                  padding: const EdgeInsets.all(18),
                  child: _MissionErrorCard(message: error!, onRetry: _load),
                )
              : ListView(
                  padding: const EdgeInsets.fromLTRB(18, 12, 18, 110),
                  children: [
                    _MissionIdentityCard(mission: m!),
                    const SizedBox(height: 14),
                    if (m.status == 'pending')
                      _PrimaryButton(
                        label: 'Commencer la mission',
                        icon: Icons.play_arrow_rounded,
                        onPressed: busy ? null : _start,
                      ),
                    if (m.status == 'pending') const SizedBox(height: 12),
                    if (m.configureLogistics)
                      _TaskCard(
                        icon: Icons.local_shipping_outlined,
                        title: 'Logistique',
                        done: m.logisticsCompleted,
                        value: m.logisticsCompleted ? m.shop.logisticsLabel : 'À configurer',
                        actionLabel: 'Configurer',
                        onTap: m.logisticsCompleted ? null : _logistics,
                      ),
                    if (m.configurePayout)
                      _TaskCard(
                        icon: Icons.account_balance_wallet_outlined,
                        title: 'Reversement',
                        done: m.payoutCompleted,
                        value: m.payoutCompleted ? 'Configuré' : 'À configurer',
                        actionLabel: 'Configurer',
                        onTap: m.payoutCompleted ? null : _payout,
                      ),
                    if (m.manageProducts)
                      _TaskCard(
                        icon: Icons.inventory_2_outlined,
                        title: 'Produits',
                        done: m.productsCompleted,
                        value: '${m.shop.productsCount} produit(s)',
                        actionLabel: 'Ouvrir',
                        onTap: m.productsCompleted ? null : _products,
                      ),
                    if (m.manageProducts && !m.productsCompleted) ...[
                      const SizedBox(height: 10),
                      _SecondaryButton(
                        label: 'Marquer les produits comme terminés',
                        icon: Icons.task_alt_rounded,
                        onPressed: busy ? null : _productsDone,
                      ),
                    ],
                    const SizedBox(height: 18),
                    _PrimaryButton(
                      label: 'Terminer la mission',
                      icon: Icons.check_circle_outline_rounded,
                      onPressed: !m.canComplete || busy ? null : _complete,
                    ),
                    const SizedBox(height: 10),
                    _SecondaryButton(
                      label: 'Retour aux missions',
                      icon: Icons.arrow_back_rounded,
                      onPressed: () => Navigator.maybePop(context),
                    ),
                  ],
                ),
    );
  }
}

class _MissionLogisticsForm extends StatefulWidget {
  const _MissionLogisticsForm({
    required this.shopsService,
    required this.mission,
    required this.communes,
  });

  final ShopsService shopsService;
  final ShopConfigurationMission mission;
  final List<CommuneOption> communes;

  @override
  State<_MissionLogisticsForm> createState() => _MissionLogisticsFormState();
}

class _MissionLogisticsFormState extends State<_MissionLogisticsForm> {
  late String mode;
  final address = TextEditingController();
  final commune = TextEditingController();
  final district = TextEditingController();
  final landmark = TextEditingController();
  final lat = TextEditingController();
  final lng = TextEditingController();
  final delay = TextEditingController(text: '24-48 h');
  final maxWeight = TextEditingController();
  final maxVolume = TextEditingController();
  final conditions = TextEditingController();
  int? zoneCommune;
  String vehicle = 'moto';
  final zonePrice = TextEditingController();
  final zoneDelay = TextEditingController(text: '24-48 h');
  bool confirmed = false;
  bool locating = false;

  @override
  void initState() {
    super.initState();
    final c = widget.mission.configuration;
    mode = widget.mission.shop.logisticsType == 'seller' ? 'seller' : 'ovanie';
    address.text = '${c['address'] ?? ''}';
    commune.text = widget.mission.shop.commune;
    district.text = widget.mission.shop.district;
    landmark.text = '${c['landmark'] ?? ''}';
    lat.text = '${c['latitude'] ?? ''}';
    lng.text = '${c['longitude'] ?? ''}';
  }

  @override
  void dispose() {
    for (final controller in [address, commune, district, landmark, lat, lng, delay, maxWeight, maxVolume, conditions, zonePrice, zoneDelay]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _gps() async {
    setState(() => locating = true);
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        throw Exception('Autorisation GPS refusée.');
      }
      final pos = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
      lat.text = pos.latitude.toStringAsFixed(7);
      lng.text = pos.longitude.toStringAsFixed(7);
      final reverse = await widget.shopsService.reverseGeocode(latitude: pos.latitude, longitude: pos.longitude);
      final resolvedAddress = reverse.address?.trim() ?? '';
      final resolvedCommune = reverse.commune?.trim() ?? '';
      final resolvedQuarter = reverse.quarter?.trim() ?? '';
      final resolvedLandmark = reverse.landmark?.trim() ?? '';
      if (resolvedAddress.isNotEmpty) address.text = resolvedAddress;
      if (resolvedCommune.isNotEmpty) commune.text = resolvedCommune;
      if (resolvedQuarter.isNotEmpty) district.text = resolvedQuarter;
      if (resolvedLandmark.isNotEmpty) landmark.text = resolvedLandmark;
      setState(() => confirmed = true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e'), behavior: SnackBarBehavior.floating),
        );
      }
    } finally {
      if (mounted) setState(() => locating = false);
    }
  }

  void _submit() {
    if (mode == 'ovanie') {
      Navigator.pop(context, {
        'logistics_type': 'ovanie',
        'address': address.text,
        'commune': commune.text,
        'district': district.text,
        'landmark': landmark.text,
        'latitude': lat.text,
        'longitude': lng.text,
        'location_confirmed': confirmed,
      });
      return;
    }

    Navigator.pop(context, {
      'logistics_type': 'seller',
      'default_delay': delay.text,
      'max_weight_kg': maxWeight.text,
      'max_volume_m3': maxVolume.text,
      'conditions': conditions.text,
      'vehicle_types': [vehicle],
      'zones': [
        {
          'commune_id': zoneCommune,
          'vehicle_code': vehicle,
          'delivery_price': zonePrice.text,
          'estimated_delay': zoneDelay.text,
        }
      ],
    });
  }

  @override
  Widget build(BuildContext context) {
    return _MissionShell(
      title: 'Configuration logistique',
      onBack: () => Navigator.maybePop(context),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 110),
        children: [
          _ShopMiniCard(shop: widget.mission.shop),
          const SizedBox(height: 14),
          _FormCard(
            title: 'Mode de livraison',
            icon: Icons.local_shipping_outlined,
            children: [
              DropdownButtonFormField<String>(
                value: mode,
                decoration: _fieldDecoration('Mode logistique'),
                items: const [
                  DropdownMenuItem(value: 'ovanie', child: Text('OVANIE Logistics')),
                  DropdownMenuItem(value: 'seller', child: Text('Ma propre logistique')),
                ],
                onChanged: (value) => setState(() => mode = value!),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (mode == 'ovanie')
            _FormCard(
              title: 'Localisation de la boutique',
              icon: Icons.location_on_outlined,
              children: [
                TextField(controller: address, decoration: _fieldDecoration('Adresse')),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(child: TextField(controller: commune, decoration: _fieldDecoration('Commune'))),
                    const SizedBox(width: 10),
                    Expanded(child: TextField(controller: district, decoration: _fieldDecoration('Quartier'))),
                  ],
                ),
                const SizedBox(height: 10),
                TextField(controller: landmark, decoration: _fieldDecoration('Point de repère')),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(child: TextField(controller: lat, decoration: _fieldDecoration('Latitude'))),
                    const SizedBox(width: 10),
                    Expanded(child: TextField(controller: lng, decoration: _fieldDecoration('Longitude'))),
                  ],
                ),
                const SizedBox(height: 12),
                _SecondaryButton(
                  label: locating ? 'Localisation...' : 'Capturer le GPS',
                  icon: Icons.my_location_rounded,
                  onPressed: locating ? null : _gps,
                ),
                const SizedBox(height: 8),
                CheckboxListTile(
                  value: confirmed,
                  activeColor: OvanieColors.blue,
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  onChanged: (value) => setState(() => confirmed = value ?? false),
                  title: const Text('Position vérifiée', style: TextStyle(fontWeight: FontWeight.w700, color: _missionText)),
                ),
              ],
            )
          else ...[
            _FormCard(
              title: 'Capacité de livraison',
              icon: Icons.inventory_2_outlined,
              children: [
                TextField(controller: delay, decoration: _fieldDecoration('Délai par défaut')),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: maxWeight,
                        keyboardType: TextInputType.number,
                        decoration: _fieldDecoration('Poids max (kg)'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextField(
                        controller: maxVolume,
                        keyboardType: TextInputType.number,
                        decoration: _fieldDecoration('Volume max (m³)'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                TextField(controller: conditions, maxLines: 3, decoration: _fieldDecoration('Conditions de livraison')),
              ],
            ),
            const SizedBox(height: 12),
            _FormCard(
              title: 'Zone de livraison',
              icon: Icons.map_outlined,
              children: [
                DropdownButtonFormField<int>(
                  value: zoneCommune,
                  decoration: _fieldDecoration('Commune'),
                  items: widget.communes
                      .map((c) => DropdownMenuItem(value: c.id, child: Text(c.name)))
                      .toList(),
                  onChanged: (value) => setState(() => zoneCommune = value),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  value: vehicle,
                  decoration: _fieldDecoration('Véhicule'),
                  items: const [
                    DropdownMenuItem(value: 'moto', child: Text('Moto')),
                    DropdownMenuItem(value: 'tricycle', child: Text('Tricycle')),
                    DropdownMenuItem(value: 'pickup', child: Text('Pickup')),
                    DropdownMenuItem(value: 'camion_3t', child: Text('Camion 3T')),
                    DropdownMenuItem(value: 'camion_10t', child: Text('Camion 10T')),
                  ],
                  onChanged: (value) => setState(() => vehicle = value!),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: zonePrice,
                        keyboardType: TextInputType.number,
                        decoration: _fieldDecoration('Tarif (FCFA)'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(child: TextField(controller: zoneDelay, decoration: _fieldDecoration('Délai'))),
                  ],
                ),
              ],
            ),
          ],
          const SizedBox(height: 18),
          _PrimaryButton(label: 'Enregistrer', icon: Icons.save_outlined, onPressed: _submit),
          const SizedBox(height: 10),
          _SecondaryButton(label: 'Annuler', icon: Icons.arrow_back_rounded, onPressed: () => Navigator.maybePop(context)),
        ],
      ),
    );
  }
}

class _MissionPayoutForm extends StatefulWidget {
  const _MissionPayoutForm({required this.mission});
  final ShopConfigurationMission mission;

  @override
  State<_MissionPayoutForm> createState() => _MissionPayoutFormState();
}

class _MissionPayoutFormState extends State<_MissionPayoutForm> {
  String mode = 'post_delivery';
  String operator = 'orange';
  final number = TextEditingController();
  final holder = TextEditingController();
  bool confirmed = false;

  @override
  void initState() {
    super.initState();
    final c = widget.mission.configuration;
    mode = '${c['payment_mode'] ?? ''}' == 'weekly' ? 'weekly' : 'post_delivery';
    operator = ['orange', 'mtn', 'wave', 'moov'].contains('${c['mm_operator']}') ? '${c['mm_operator']}' : 'orange';
    number.text = '${c['mm_number'] ?? ''}';
    holder.text = '${c['mm_holder'] ?? ''}';
  }

  @override
  void dispose() {
    number.dispose();
    holder.dispose();
    super.dispose();
  }

  void _submit() {
    Navigator.pop(context, {
      'payment_mode': mode,
      'mm_operator': operator,
      'mm_number': number.text,
      'mm_holder': holder.text,
      'seller_confirmation': true,
    });
  }

  @override
  Widget build(BuildContext context) {
    return _MissionShell(
      title: 'Configuration reversement',
      onBack: () => Navigator.maybePop(context),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 110),
        children: [
          _ShopMiniCard(shop: widget.mission.shop),
          const SizedBox(height: 14),
          _FormCard(
            title: 'Mode de reversement',
            icon: Icons.payments_outlined,
            children: [
              DropdownButtonFormField<String>(
                value: mode,
                decoration: _fieldDecoration('Calendrier'),
                items: const [
                  DropdownMenuItem(value: 'post_delivery', child: Text('Après livraison — 72 h')),
                  DropdownMenuItem(value: 'weekly', child: Text('Hebdomadaire')),
                ],
                onChanged: (value) => setState(() => mode = value!),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _FormCard(
            title: 'Compte de paiement',
            icon: Icons.account_balance_wallet_outlined,
            children: [
              DropdownButtonFormField<String>(
                value: operator,
                decoration: _fieldDecoration('Opérateur'),
                items: const [
                  DropdownMenuItem(value: 'orange', child: Text('Orange Money')),
                  DropdownMenuItem(value: 'mtn', child: Text('MTN MoMo')),
                  DropdownMenuItem(value: 'wave', child: Text('Wave')),
                  DropdownMenuItem(value: 'moov', child: Text('Moov Money')),
                ],
                onChanged: (value) => setState(() => operator = value!),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: number,
                keyboardType: TextInputType.phone,
                decoration: _fieldDecoration('Numéro de reversement'),
              ),
              const SizedBox(height: 10),
              TextField(controller: holder, decoration: _fieldDecoration('Nom du titulaire')),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            decoration: BoxDecoration(
              color: _missionCard,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: _missionLine),
            ),
            child: CheckboxListTile(
              value: confirmed,
              activeColor: OvanieColors.blue,
              controlAffinity: ListTileControlAffinity.leading,
              onChanged: (value) => setState(() => confirmed = value ?? false),
              title: const Text('Coordonnées confirmées avec le vendeur', style: TextStyle(fontWeight: FontWeight.w700, color: _missionText)),
            ),
          ),
          const SizedBox(height: 18),
          _PrimaryButton(
            label: 'Enregistrer le reversement',
            icon: Icons.check_circle_outline_rounded,
            onPressed: confirmed ? _submit : null,
          ),
          const SizedBox(height: 10),
          _SecondaryButton(label: 'Annuler', icon: Icons.arrow_back_rounded, onPressed: () => Navigator.maybePop(context)),
        ],
      ),
    );
  }
}

class _MissionShell extends StatelessWidget {
  const _MissionShell({required this.title, required this.child, required this.onBack});
  final String title;
  final Widget child;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = (width / 472).clamp(.78, 1.08).toDouble();
    double s(double value) => value * scale;
    final profile = CommercialProfile.fromJson(const {
      'name': 'Commercial OVANIE',
      'first_name': 'commercial',
    });

    return Scaffold(
      extendBody: true,
      body: BrandBackground(
        child: SafeArea(
          bottom: false,
          child: Column(
            children: [
              Padding(
                padding: EdgeInsets.fromLTRB(s(16), s(8), s(16), 0),
                child: CommercialClientsHeader(
                  profile: profile,
                  unreadNotifications: 0,
                  scale: scale,
                  onNotificationsTap: () {},
                  onAvatarTap: () {},
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(s(18), s(7), s(18), s(8)),
                child: Row(
                  children: [
                    InkWell(
                      onTap: onBack,
                      borderRadius: BorderRadius.circular(s(12)),
                      child: Container(
                        width: s(42),
                        height: s(42),
                        decoration: BoxDecoration(
                          color: const Color(0xFF0B2B57),
                          borderRadius: BorderRadius.circular(s(12)),
                          border: Border.all(color: const Color(0xFF315A88)),
                        ),
                        child: Icon(Icons.arrow_back_rounded, color: Colors.white, size: s(22)),
                      ),
                    ),
                    SizedBox(width: s(12)),
                    Expanded(
                      child: Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: s(24),
                          fontWeight: FontWeight.w900,
                          letterSpacing: -.4,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(child: child),
            ],
          ),
        ),
      ),
      bottomNavigationBar: CommercialBottomNavigation(
        scale: scale,
        currentIndex: 2,
        onTap: (index) {
          if (index == 2) {
            Navigator.of(context).popUntil((route) => route.isFirst);
            CommercialTabBus.request(2);
            return;
          }
          CommercialTabBus.request(index);
        },
      ),
    );
  }
}

class _MissionIdentityCard extends StatelessWidget {
  const _MissionIdentityCard({required this.mission});
  final ShopConfigurationMission mission;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: _missionCard,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _missionLine),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(color: _missionSoftBlue, borderRadius: BorderRadius.circular(15)),
                child: const Icon(Icons.storefront_outlined, color: OvanieColors.blue),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(mission.shop.name, style: const TextStyle(color: _missionText, fontWeight: FontWeight.w900, fontSize: 20)),
                    const SizedBox(height: 4),
                    Text(mission.shop.ownerName, style: const TextStyle(color: _missionMuted, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
              _StatusPill(text: _missionStatus(mission.status)),
            ],
          ),
          const SizedBox(height: 16),
          _DetailLine(icon: Icons.phone_outlined, text: mission.shop.ownerPhone.isEmpty ? 'Téléphone non renseigné' : mission.shop.ownerPhone),
          const SizedBox(height: 8),
          _DetailLine(icon: Icons.location_on_outlined, text: _location(mission.shop)),
          if (mission.notes.trim().isNotEmpty) ...[
            const Divider(height: 26),
            Text(mission.notes, style: const TextStyle(color: _missionText, fontWeight: FontWeight.w600)),
          ],
        ],
      ),
    );
  }
}

class _ShopMiniCard extends StatelessWidget {
  const _ShopMiniCard({required this.shop});
  final MissionShop shop;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: _missionCard, borderRadius: BorderRadius.circular(16), border: Border.all(color: _missionLine)),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(color: _missionSoftBlue, borderRadius: BorderRadius.circular(14)),
              child: const Icon(Icons.storefront_outlined, color: OvanieColors.blue),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(shop.name, style: const TextStyle(color: _missionText, fontSize: 17, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 3),
                  Text(shop.ownerName, style: const TextStyle(color: _missionMuted, fontWeight: FontWeight.w600)),
                ],
              ),
            ),
          ],
        ),
      );
}

class _MissionCard extends StatelessWidget {
  const _MissionCard({required this.mission, required this.onTap});
  final ShopConfigurationMission mission;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: _missionCard,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(17),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(16), border: Border.all(color: _missionLine)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(color: _missionSoftBlue, borderRadius: BorderRadius.circular(14)),
                    child: const Icon(Icons.storefront_outlined, color: OvanieColors.blue),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(mission.shop.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: _missionText, fontWeight: FontWeight.w900, fontSize: 17)),
                        const SizedBox(height: 4),
                        Text(mission.shop.ownerName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: _missionMuted, fontWeight: FontWeight.w600, fontSize: 13)),
                      ],
                    ),
                  ),
                  _StatusPill(text: _missionStatus(mission.status)),
                ],
              ),
              const SizedBox(height: 14),
              _DetailLine(icon: Icons.location_on_outlined, text: _location(mission.shop)),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (mission.configureLogistics)
                    _StateChip(text: mission.logisticsCompleted ? 'Logistique configurée' : 'Logistique à configurer', done: mission.logisticsCompleted),
                  if (mission.configurePayout)
                    _StateChip(text: mission.payoutCompleted ? 'Reversement configuré' : 'Reversement à configurer', done: mission.payoutCompleted),
                  if (mission.manageProducts)
                    _StateChip(text: '${mission.shop.productsCount} produit(s)', done: mission.productsCompleted),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  const Spacer(),
                  FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: onTap,
                    icon: const Icon(Icons.arrow_forward_rounded, size: 17),
                    label: const Text('Ouvrir', style: TextStyle(fontWeight: FontWeight.w800)),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TaskCard extends StatelessWidget {
  const _TaskCard({
    required this.icon,
    required this.title,
    required this.done,
    required this.value,
    required this.actionLabel,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final bool done;
  final String value;
  final String actionLabel;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(color: _missionCard, borderRadius: BorderRadius.circular(16), border: Border.all(color: _missionLine)),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(color: done ? _missionGreenBg : _missionOrangeBg, borderRadius: BorderRadius.circular(14)),
            child: Icon(done ? Icons.check_rounded : icon, color: done ? _missionGreen : OvanieColors.orange),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: _missionText, fontWeight: FontWeight.w900, fontSize: 16)),
                const SizedBox(height: 4),
                Text(value, style: const TextStyle(color: _missionMuted, fontWeight: FontWeight.w600, fontSize: 13)),
              ],
            ),
          ),
          if (onTap != null)
            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFFFF0E3),
                foregroundColor: OvanieColors.orange,
                elevation: 0,
                padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: onTap,
              child: Text(actionLabel, style: const TextStyle(fontWeight: FontWeight.w800)),
            ),
        ],
      ),
    );
  }
}

class _FormCard extends StatelessWidget {
  const _FormCard({required this.title, required this.icon, required this.children});
  final String title;
  final IconData icon;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: _missionCard, borderRadius: BorderRadius.circular(16), border: Border.all(color: _missionLine)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(color: _missionSoftBlue, borderRadius: BorderRadius.circular(12)),
                  child: Icon(icon, color: OvanieColors.blue, size: 21),
                ),
                const SizedBox(width: 10),
                Text(title, style: const TextStyle(color: _missionText, fontSize: 16, fontWeight: FontWeight.w900)),
              ],
            ),
            const SizedBox(height: 16),
            ...children,
          ],
        ),
      );
}

class _SummaryBox extends StatelessWidget {
  const _SummaryBox({required this.value, required this.label});
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 15),
        decoration: BoxDecoration(
          color: const Color(0xFF082A5A),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFF24558F)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(value, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
            const SizedBox(height: 3),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xFFB9C8DE), fontSize: 11, fontWeight: FontWeight.w700)),
          ],
        ),
      );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(color: _missionSoftBlue, borderRadius: BorderRadius.circular(999)),
        child: Text(text, style: const TextStyle(color: OvanieColors.blue, fontWeight: FontWeight.w800, fontSize: 11)),
      );
}

class _StateChip extends StatelessWidget {
  const _StateChip({required this.text, required this.done});
  final String text;
  final bool done;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
        decoration: BoxDecoration(color: done ? _missionGreenBg : _missionOrangeBg, borderRadius: BorderRadius.circular(999)),
        child: Text(text, style: TextStyle(color: done ? _missionGreen : const Color(0xFFAA5C05), fontSize: 11, fontWeight: FontWeight.w800)),
      );
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
        children: [
          Icon(icon, size: 18, color: OvanieColors.blue),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: const TextStyle(color: _missionMuted, fontWeight: FontWeight.w600, fontSize: 13))),
        ],
      );
}

class _PrimaryButton extends StatelessWidget {
  const _PrimaryButton({required this.label, required this.icon, required this.onPressed});
  final String label;
  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => FilledButton.icon(
        style: FilledButton.styleFrom(
          backgroundColor: OvanieColors.orange,
          foregroundColor: Colors.white,
          disabledBackgroundColor: const Color(0xFF49607F),
          minimumSize: const Size.fromHeight(54),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
        onPressed: onPressed,
        icon: Icon(icon),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
      );
}

class _SecondaryButton extends StatelessWidget {
  const _SecondaryButton({required this.label, required this.icon, required this.onPressed});
  final String label;
  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => OutlinedButton.icon(
        style: OutlinedButton.styleFrom(
          foregroundColor: Colors.white,
          side: const BorderSide(color: Color(0xFF3D6798)),
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
        onPressed: onPressed,
        icon: Icon(icon),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
      );
}

class _MissionProductsReturnBar extends StatelessWidget {
  const _MissionProductsReturnBar({required this.onBack});
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(14, 8, 14, 4),
        child: OutlinedButton.icon(
          style: OutlinedButton.styleFrom(
            foregroundColor: Colors.white,
            side: const BorderSide(color: Color(0xFF315B8B)),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
          onPressed: onBack,
          icon: const Icon(Icons.arrow_back_rounded, size: 18),
          label: const Text('Retour à la mission', style: TextStyle(fontWeight: FontWeight.w800)),
        ),
      );
}

class _MissionErrorCard extends StatelessWidget {
  const _MissionErrorCard({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(color: _missionCard, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFF1B4B4))),
        child: Column(
          children: [
            const Icon(Icons.error_outline_rounded, color: Color(0xFFC74343), size: 34),
            const SizedBox(height: 10),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(color: _missionText, fontWeight: FontWeight.w700)),
            const SizedBox(height: 14),
            TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded), label: const Text('Réessayer')),
          ],
        ),
      );
}

class _LoadingCard extends StatelessWidget {
  const _LoadingCard();

  @override
  Widget build(BuildContext context) => Container(
        height: 180,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: _missionCard, borderRadius: BorderRadius.circular(16), border: Border.all(color: _missionLine)),
        child: const CircularProgressIndicator(),
      );
}

class _EmptyMissionCard extends StatelessWidget {
  const _EmptyMissionCard();

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 38),
        decoration: BoxDecoration(color: _missionCard, borderRadius: BorderRadius.circular(16), border: Border.all(color: _missionLine)),
        child: const Column(
          children: [
            Icon(Icons.assignment_turned_in_outlined, color: OvanieColors.blue, size: 42),
            SizedBox(height: 12),
            Text('Aucune mission en cours', style: TextStyle(color: _missionText, fontWeight: FontWeight.w900, fontSize: 17)),
          ],
        ),
      );
}

InputDecoration _fieldDecoration(String label) => InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: _missionMuted, fontWeight: FontWeight.w600),
      filled: true,
      fillColor: const Color(0xFFFAFCFF),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: _missionLine)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: OvanieColors.blue, width: 1.5)),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
    );
