import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_widgets.dart' show OrderMapImage;
import 'logistics_settings_screen.dart';
import 'menu_ui.dart';

/// Véhicules de livraison proposés au vendeur (code serveur → libellé).
const deliveryVehicles = <String, String>{
  'moto': 'Moto',
  'tricycle': 'Tricycle',
  'pickup': 'Pickup',
  'camion_3t': 'Camion 3 t',
  'camion_10t': 'Camion 10 t',
};

String deliveryVehicleLabel(dynamic code) => deliveryVehicles['$code'] ?? screenText(code, 'Véhicule non précisé');

/// Livraison & logistique : mode actuel, délai de traitement et disponibilité de préparation.
class DeliverySettingsScreen extends StatefulWidget {
  const DeliverySettingsScreen({super.key});
  @override
  State<DeliverySettingsScreen> createState() => _DeliveryState();
}

class _DeliveryState extends State<DeliverySettingsScreen> {
  Map<String, dynamic> shop = {};
  bool loading = true, saving = false;
  String? error;
  String mode = '', delay = '24_48h';
  Set<int> days = {};
  TimeOfDay? start, end;

  static const delays = {
    'lt24h': 'Moins de 24 h',
    '24_48h': '24 à 48 h',
    '3_5j': '3 à 5 jours',
    '7j_plus': '7 jours et plus',
  };
  static const dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      shop = mapOf((await menuApi('shop'))['shop']);
      mode = shop['logistics_configured'] == true ? screenText(shop['logistics_type'], '') : '';
      final savedDelay = screenText(shop['processing_time'], '24_48h');
      delay = delays.containsKey(savedDelay) ? savedDelay : '24_48h';
      final p = mapOf(mapOf(shop['presentation'])['preparation']);
      days = ((p['days'] as List?) ?? []).map((e) => number(e).toInt()).toSet();
      TimeOfDay? parse(dynamic v) {
        final s = '$v'.split(':');
        final hour = s.length >= 2 ? int.tryParse(s[0]) : null;
        final minute = s.length >= 2 ? int.tryParse(s[1]) : null;
        return hour == null || minute == null ? null : TimeOfDay(hour: hour, minute: minute);
      }

      start = parse(p['start']);
      end = parse(p['end']);
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String time(TimeOfDay? t) =>
      t == null ? 'Choisir' : '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> pickTime(bool first) async {
    final t = await showTimePicker(
      context: context,
      helpText: first ? 'Heure de début' : 'Heure limite',
      cancelText: 'Annuler',
      confirmText: 'Valider',
      initialTime: (first ? start : end) ?? TimeOfDay(hour: first ? 8 : 17, minute: 0),
    );
    if (t != null && mounted) {
      setState(() {
        if (first) {
          start = t;
        } else {
          end = t;
        }
      });
    }
  }

  Future<void> save() async {
    String? problem;
    if (days.isEmpty) {
      problem = 'Choisissez au moins un jour de préparation.';
    } else if (start == null || end == null) {
      problem = 'Choisissez l’heure de début et l’heure limite.';
    } else if (end!.hour * 60 + end!.minute <= start!.hour * 60 + start!.minute) {
      problem = 'L’heure limite doit être après l’heure de début.';
    }
    if (problem != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(problem)));
      return;
    }
    setState(() => saving = true);
    try {
      await menuApi(
        'shop/preparation-settings',
        method: 'POST',
        data: {'processing_time': delay, 'days': days.toList()..sort(), 'start': time(start), 'end': time(end)},
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Paramètres enregistrés.')));
      }
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> editLogistics({bool change = false, String? targetMode}) async {
    final target = targetMode ?? (change ? (mode == 'seller' ? 'ovanie' : 'seller') : mode);
    if (target.isEmpty) return;
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => LogisticsSettingsScreen(shop: shop, targetMode: target)),
    );
    if (!mounted) return;
    if (saved == true) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Informations logistiques enregistrées.')));
    }
    await load();
  }

  @override
  Widget build(BuildContext context) => MenuPage(
    title: 'Livraison & logistique',
    subtitle: 'Configurez votre mode logistique et vos délais de traitement',
    refresh: load,
    loading: loading,
    error: error,
    hasContent: shop.isNotEmpty,
    actions: [
      MkBarButton(label: 'Enregistrer les paramètres', filled: true, loading: saving, onTap: saving ? null : save),
    ],
    children: [
      _modeCard(),
      _delayCard(),
      _availabilityCard(),
      if (mode == 'seller') ...[
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () async {
                await Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const DeliveryZonesScreen()));
                if (mounted) await load();
              },
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    menuIcon(Icons.location_on_outlined, size: 42),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          menuHeading('Zones de livraison'),
                          const Text(
                            'Communes couvertes, tarifs et délais',
                            style: TextStyle(color: menuMuted, fontSize: 13, fontWeight: FontWeight.w500),
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
        ),
        menuInfo('Les capacités de transport et les tarifs par commune doivent être configurés pour la logistique vendeur.'),
      ],
    ],
  );

  // ---- Mode logistique -----------------------------------------------------------
  Widget _modeCard() {
    final seller = mode == 'seller';
    final wide = MediaQuery.sizeOf(context).width >= 360;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(14, 16, 4, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const OrderCaption('Mode actuel'),
                      const SizedBox(height: 6),
                      Text(
                        mode.isEmpty ? 'À configurer' : (seller ? 'Ma propre logistique' : 'OVANIE Logistics'),
                        style: const TextStyle(color: menuBlue, fontSize: 20, height: 1.2, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 8),
                      mode.isEmpty
                          ? menuPill('Choix à confirmer', color: const Color(0xFFE85400), icon: Icons.info_rounded)
                          : menuPill('Mode actif', color: menuGreen, icon: Icons.check_circle_rounded),
                    ],
                  ),
                ),
              ),
              if (wide)
                SizedBox(
                  width: 150,
                  height: 118,
                  child: Image.asset(
                    seller ? 'assets/images/mockups/logi_pickup.jpg' : 'assets/images/mockups/logi_ovanie.jpg',
                    fit: BoxFit.cover,
                    alignment: Alignment.centerLeft,
                    excludeFromSemantics: true,
                  ),
                ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 2, 14, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (mode.isEmpty) ...[
                  const Text(
                    'Choisissez le mode logistique de votre boutique. Aucun mode n’est actif tant que vous ne l’avez pas confirmé.',
                    style: TextStyle(color: menuMuted, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 12),
                  MkBarButton(
                    label: 'Choisir OVANIE Logistics',
                    filled: true,
                    onTap: saving ? null : () => editLogistics(targetMode: 'ovanie'),
                  ),
                  const SizedBox(height: 8),
                  MkBarButton(
                    label: 'Choisir ma propre logistique',
                    color: orderBlue,
                    onTap: saving ? null : () => editLogistics(targetMode: 'seller'),
                  ),
                ] else ...[
                  Row(
                    children: [
                      Expanded(
                        child: _modeButton(
                          Icons.swap_horiz_rounded,
                          'Changer de mode logistique',
                          saving ? null : () => editLogistics(change: true),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _modeButton(
                          Icons.settings_outlined,
                          'Modifier mes informations logistiques',
                          saving ? null : () => editLogistics(),
                        ),
                      ),
                    ],
                  ),
                  const Divider(height: 24, color: orderBorder),
                  const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.info_rounded, color: Mk.hint, size: 20),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Les commandes déjà créées ou en cours conservent leur mode logistique.',
                          style: TextStyle(color: menuMuted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _modeButton(IconData icon, String label, VoidCallback? onTap) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(12),
      side: const BorderSide(color: orderBlue, width: 1.2),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Container(
        constraints: const BoxConstraints(minHeight: 54),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        child: Row(
          children: [
            Icon(icon, color: orderBlue, size: 22),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(color: orderBlue, fontSize: 12.5, height: 1.25, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
      ),
    ),
  );

  // ---- Délai de traitement ----------------------------------------------------------
  Widget _delayCard() => DataCard(
    title: 'Délai de traitement',
    icon: Icons.schedule_rounded,
    iconColor: menuOrange,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Sélectionnez le délai nécessaire avant la prise en charge de la commande.',
          style: TextStyle(color: menuMuted, fontSize: 13, height: 1.4, fontWeight: FontWeight.w500),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final entry in delays.entries) _delayOption(entry.key, entry.value),
          ],
        ),
      ],
    ),
  );

  Widget _delayOption(String key, String label) {
    final selected = delay == key;
    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      child: Material(
        color: selected ? Mk.softOrange : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(11),
          side: BorderSide(color: selected ? Mk.orange : orderBorder, width: selected ? 1.5 : 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: saving ? null : () => setState(() => delay = key),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 11),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  selected ? Icons.radio_button_checked_rounded : Icons.radio_button_unchecked_rounded,
                  color: selected ? Mk.orange : Mk.hint,
                  size: 20,
                ),
                const SizedBox(width: 7),
                Text(
                  label,
                  style: TextStyle(
                    color: selected ? const Color(0xFFE85400) : Mk.icon,
                    fontSize: 13.5,
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ---- Disponibilité de préparation ----------------------------------------------------
  Widget _availabilityCard() => DataCard(
    title: 'Disponibilité de préparation',
    icon: Icons.calendar_month_outlined,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Sept cases de largeur égale : la sélection ne déplace pas les autres jours.
        Row(
          children: [
            for (var i = 0; i < 7; i++) ...[
              if (i > 0) const SizedBox(width: 4),
              Expanded(child: _dayToggle(i + 1, dayNames[i])),
            ],
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(child: _timeField('Heure de début', start, () => pickTime(true))),
            const SizedBox(width: 8),
            Expanded(child: _timeField('Heure limite', end, () => pickTime(false))),
          ],
        ),
      ],
    ),
  );

  Widget _dayToggle(int day, String label) {
    final selected = days.contains(day);
    return Semantics(
      checked: selected,
      button: true,
      label: label,
      child: Material(
        color: selected ? const Color(0xFF0B4FC4) : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: BorderSide(color: selected ? const Color(0xFF0B4FC4) : orderBorder, width: 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: saving
              ? null
              : () => setState(() {
                  if (selected) {
                    days.remove(day);
                  } else {
                    days.add(day);
                  }
                }),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 7),
            child: ExcludeSemantics(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    selected ? Icons.check_circle_rounded : Icons.circle_outlined,
                    color: selected ? Colors.white : const Color(0xFFC5CEDE),
                    size: 15,
                  ),
                  const SizedBox(height: 3),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      label,
                      maxLines: 1,
                      style: TextStyle(
                        color: selected ? Colors.white : Mk.icon,
                        fontSize: 13,
                        fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _timeField(String label, TimeOfDay? value, VoidCallback onTap) => Semantics(
    button: true,
    label: '$label : ${time(value)}',
    child: Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: Mk.border, width: 1.2),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: saving ? null : onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(10, 9, 6, 9),
          child: ExcludeSemantics(
            child: Row(
              children: [
                const Icon(Icons.schedule_rounded, color: Mk.icon, size: 21),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
                      ),
                      Text(
                        time(value),
                        style: TextStyle(
                          color: value == null ? Mk.hint : menuBlue,
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.keyboard_arrow_down_rounded, color: Mk.icon, size: 22),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

/// Zones de livraison du vendeur : carte d'ensemble, recherche, filtres et liste.
class DeliveryZonesScreen extends StatefulWidget {
  const DeliveryZonesScreen({super.key, this.configuring = false});
  final bool configuring;
  @override
  State<DeliveryZonesScreen> createState() => _ZonesState();
}

class _ZonesState extends State<DeliveryZonesScreen> {
  Map<String, dynamic> shop = {}, data = {};
  bool loading = true;
  String? error;
  String query = '';
  int filter = 0;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final r = await Future.wait([
        menuApi('shop'),
        menuApi('shop/delivery${widget.configuring ? '?configure=1' : ''}'),
      ]);
      shop = mapOf(r[0]['shop']);
      data = r[1];
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> edit([Map<String, dynamic>? zone]) async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => DeliveryZoneEditScreen(shop: shop, zone: zone, areaMapUrl: screenText(data['area_map_url'], '')),
      ),
    );
    if (!mounted) return;
    if (saved == true) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Zone de livraison enregistrée.')));
    }
    await load();
  }

  @override
  Widget build(BuildContext context) {
    final all = rowsOf(data['zones']);
    final canEdit = data['uses_seller_logistics'] == true || widget.configuring;
    final rows = all
        .where(
          (z) =>
              '${z['commune']} ${z['city']} ${z['district']}'.toLowerCase().contains(query.trim().toLowerCase()) &&
              (filter == 0 || filter == 1 && z['is_active'] == true || filter == 2 && z['is_active'] != true),
        )
        .toList();
    final active = all.where((z) => z['is_active'] == true).length;
    return MenuPage(
      title: 'Zones de livraison',
      subtitle: 'Gérez les zones géographiques couvertes par votre boutique',
      refresh: load,
      loading: loading,
      error: error,
      hasContent: data.isNotEmpty,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: AspectRatio(
              aspectRatio: 16 / 9,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  OrderMapImage(url: screenText(data['area_map_url'], '')),
                  Positioned(left: 8, top: 8, child: _mapCounter(Icons.map_outlined, menuGreen, 'Zones actives', active)),
                  Positioned(
                    right: 8,
                    top: 8,
                    child: _mapCounter(Icons.schedule_rounded, Mk.icon, 'Zones suspendues', all.length - active),
                  ),
                ],
              ),
            ),
          ),
        ),
        menuSearch((v) => setState(() => query = v), 'Rechercher une zone…'),
        menuFilters(['Toutes', 'Actives', 'Suspendues'], filter, (v) => setState(() => filter = v)),
        if (canEdit)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: MkBarButton(label: 'Ajouter une zone', icon: Icons.add_rounded, filled: true, onTap: () => edit()),
          )
        else
          menuInfo('OVANIE Logistics gère la couverture et les tarifs de livraison de votre boutique.'),
        menuCaption('Liste des zones'),
        if (rows.isEmpty)
          menuEmpty(
            all.isEmpty ? 'Aucune zone configurée' : 'Aucune zone ne correspond',
            icon: Icons.location_off_outlined,
            hint: all.isEmpty
                ? (canEdit
                      ? 'Ajoutez une première zone avec son véhicule, son délai et son tarif.'
                      : 'Les zones apparaissent ici lorsque vous livrez vous-même vos commandes.')
                : 'Modifiez la recherche ou le filtre.',
          ),
        for (final z in rows) _zoneCard(z, canEdit),
        menuInfo('Les zones informent les clients sur la couverture géographique, les délais et les frais de livraison.'),
      ],
    );
  }

  Widget _mapCounter(IconData icon, Color color, String label, int value) => Container(
    padding: const EdgeInsets.fromLTRB(8, 7, 12, 7),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      boxShadow: const [BoxShadow(color: Color(0x22062962), blurRadius: 10, offset: Offset(0, 3))],
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        menuIcon(icon, color: color, size: 34),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(color: color, fontSize: 11.5, fontWeight: FontWeight.w600)),
            Text('$value', style: TextStyle(color: color, fontSize: 17, height: 1.15, fontWeight: FontWeight.w800)),
          ],
        ),
      ],
    ),
  );

  Widget _zoneCard(Map<String, dynamic> z, bool canEdit) {
    final active = z['is_active'] == true;
    final city = screenText(z['city'], '');
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: canEdit ? () => edit(z) : null,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 6, 12),
            child: Row(
              children: [
                menuIcon(Icons.location_on_rounded, size: 44),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              screenText(z['commune'], 'Commune'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: menuBlue, fontSize: 16, fontWeight: FontWeight.w800),
                            ),
                          ),
                          const SizedBox(width: 8),
                          active
                              ? menuPill('Active', color: menuGreen, icon: Icons.check_circle_rounded)
                              : menuPill('Suspendue', color: Mk.icon, icon: Icons.remove_circle_rounded),
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        [if (city.isNotEmpty) city, deliveryVehicleLabel(z['vehicle_code'])].join(' • '),
                        style: const TextStyle(color: menuMuted, fontSize: 13, fontWeight: FontWeight.w500),
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 16,
                        runSpacing: 4,
                        children: [
                          _fact(Icons.schedule_rounded, screenText(z['estimated_delay'], 'Délai à préciser')),
                          _fact(
                            Icons.payments_outlined,
                            z['delivery_price'] == null ? 'Tarif à préciser' : orderMoney(z['delivery_price']),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                if (canEdit) const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _fact(IconData icon, String text) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, color: menuOrange, size: 18),
      const SizedBox(width: 6),
      Text(text, style: const TextStyle(color: menuBlue, fontSize: 13.5, fontWeight: FontWeight.w600)),
    ],
  );
}

/// Ajout ou modification d'une zone : commune, véhicule, délai, tarif et activation.
class DeliveryZoneEditScreen extends StatefulWidget {
  const DeliveryZoneEditScreen({super.key, required this.shop, this.zone, this.areaMapUrl = ''});
  final Map<String, dynamic> shop;
  final Map<String, dynamic>? zone;
  final String areaMapUrl;
  @override
  State<DeliveryZoneEditScreen> createState() => _ZoneEditState();
}

class _ZoneEditState extends State<DeliveryZoneEditScreen> {
  final form = GlobalKey<FormState>();
  final price = TextEditingController();
  List<Map<String, dynamic>> communes = [];
  int? commune;
  String? vehicle, delay;
  bool active = true, loading = true, saving = false, submitted = false;
  String? error;

  static const _delays = ['24 h', '48 h', '72 h', '3 à 5 jours', '7 jours'];

  @override
  void initState() {
    super.initState();
    final z = widget.zone ?? {};
    final amount = z['delivery_price'] == null ? null : number(z['delivery_price']);
    price.text = amount == null ? '' : (amount == amount.roundToDouble() ? amount.round().toString() : '$amount');
    final savedDelay = screenText(z['estimated_delay'], '');
    delay = savedDelay.isEmpty ? null : savedDelay;
    commune = z['commune_id'] == null ? null : number(z['commune_id']).toInt();
    vehicle = deliveryVehicles.containsKey('${z['vehicle_code']}') ? '${z['vehicle_code']}' : null;
    active = z['is_active'] != false;
    load();
  }

  @override
  void dispose() {
    price.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      communes = rowsOf((await VendorRepository.instance.meta())['communes']);
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String? get communeName {
    for (final c in communes) {
      if (number(c['id']).toInt() == commune) return screenText(c['name'], '');
    }
    return null;
  }

  Future<void> pickCommune() async {
    final picked = await menuPick<int>(
      context,
      'Commune couverte',
      {for (final c in communes) number(c['id']).toInt(): screenText(c['name'], 'Commune')},
      commune,
    );
    if (picked != null && mounted) setState(() => commune = picked);
  }

  Future<void> pickDelay() async {
    final options = {
      for (final value in [..._delays, if (delay != null && !_delays.contains(delay)) delay!]) value: value,
    };
    final picked = await menuPick<String>(context, 'Délai de livraison', options, delay);
    if (picked != null && mounted) setState(() => delay = picked);
  }

  Future<void> save() async {
    setState(() => submitted = true);
    final valid = form.currentState!.validate();
    if (!valid || commune == null || vehicle == null || delay == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Complétez la commune, le véhicule, le délai et le tarif.')),
      );
      return;
    }
    setState(() => saving = true);
    try {
      await menuApi(
        'shop/delivery/zones${widget.zone == null ? '' : '/${widget.zone!['id']}'}',
        method: widget.zone == null ? 'POST' : 'PATCH',
        data: {
          'commune_id': commune,
          'vehicle_code': vehicle,
          'delivery_price': price.text.trim(),
          'estimated_delay': delay,
          'is_active': active,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> openMap() async {
    try {
      await launchUrl(
        Uri.https('www.google.com', '/maps/search/', {'api': '1', 'query': '$communeName, Abidjan, Côte d’Ivoire'}),
      );
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  Widget _missing(String text) => Padding(
    padding: const EdgeInsets.only(top: 6, left: 4),
    child: Text(text, style: const TextStyle(color: Mk.danger, fontSize: 12.5, fontWeight: FontWeight.w600)),
  );

  @override
  Widget build(BuildContext context) => MenuPage(
    title: widget.zone == null ? 'Ajouter une zone' : 'Modifier une zone',
    subtitle: 'Définissez la couverture, les délais et les frais de livraison',
    refresh: load,
    loading: loading,
    error: error,
    hasContent: communes.isNotEmpty,
    actions: [
      MkBarButton(label: 'Annuler', color: Mk.navy, onTap: saving ? null : () => Navigator.pop(context)),
      MkBarButton(label: 'Enregistrer la zone', filled: true, loading: saving, onTap: saving ? null : save),
    ],
    children: [
      Form(
        key: form,
        child: DataCard(
          title: 'Informations de la zone',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const MkLabel('Commune couverte', required: true),
              const SizedBox(height: 6),
              MenuSelect(
                value: communeName,
                hint: 'Choisir une commune',
                icon: Icons.location_on_rounded,
                error: submitted && commune == null,
                onTap: saving ? null : pickCommune,
              ),
              if (submitted && commune == null) _missing('Choisissez une commune.'),
              const SizedBox(height: 14),
              const MkLabel('Véhicule de livraison', required: true),
              const SizedBox(height: 6),
              SizedBox(
                height: 104,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: deliveryVehicles.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 8),
                  itemBuilder: (context, i) {
                    final entry = deliveryVehicles.entries.elementAt(i);
                    return _vehicleCard(entry.key, entry.value);
                  },
                ),
              ),
              if (submitted && vehicle == null) _missing('Choisissez un véhicule.'),
              const SizedBox(height: 14),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const MkLabel('Délai de livraison', required: true),
                        const SizedBox(height: 6),
                        MenuSelect(
                          value: delay,
                          hint: 'Choisir',
                          icon: Icons.schedule_rounded,
                          error: submitted && delay == null,
                          onTap: saving ? null : pickDelay,
                        ),
                        if (submitted && delay == null) _missing('Choisissez un délai.'),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const MkLabel('Frais de livraison', required: true),
                        const SizedBox(height: 6),
                        TextFormField(
                          controller: price,
                          enabled: !saving,
                          keyboardType: TextInputType.number,
                          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(8)],
                          style: mkFieldText,
                          decoration: mkInput(
                            hint: '5000',
                            icon: Icons.payments_outlined,
                            suffix: const Center(
                              widthFactor: 1,
                              child: Padding(
                                padding: EdgeInsets.only(right: 10),
                                child: Text('FCFA', style: TextStyle(color: menuMuted, fontSize: 13, fontWeight: FontWeight.w600)),
                              ),
                            ),
                          ),
                          validator: (v) => int.tryParse((v ?? '').trim()) == null ? 'Montant invalide' : null,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: 26, color: orderBorder),
              MergeSemantics(
                child: Row(
                  children: [
                    Switch(
                      value: active,
                      activeTrackColor: Mk.orange,
                      activeThumbColor: Colors.white,
                      onChanged: saving ? null : (v) => setState(() => active = v),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        active ? 'Zone active' : 'Zone suspendue',
                        style: const TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
      DataCard(
        title: 'Couverture géographique',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: AspectRatio(
                aspectRatio: 16 / 9,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    OrderMapImage(url: widget.areaMapUrl),
                    if (communeName != null)
                      Positioned(
                        left: 8,
                        top: 8,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(color: Mk.orange, borderRadius: BorderRadius.circular(99)),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.location_on_rounded, color: Colors.white, size: 16),
                              const SizedBox(width: 4),
                              Text(
                                communeName!,
                                style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 10),
            MkBarButton(
              label: 'Voir la commune sur la carte',
              icon: Icons.map_outlined,
              color: orderBlue,
              onTap: communeName == null ? null : openMap,
            ),
          ],
        ),
      ),
      menuInfo('Un tarif est enregistré par commune et par type de véhicule pour calculer les frais de livraison des commandes.'),
    ],
  );

  Widget _vehicleCard(String code, String label) {
    final selected = vehicle == code;
    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      label: label,
      child: Material(
        color: selected ? Mk.softOrange : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: selected ? Mk.orange : orderBorder, width: selected ? 1.6 : 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: saving ? null : () => setState(() => vehicle = code),
          child: SizedBox(
            width: 96,
            child: Stack(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(8, 8, 8, 8),
                  child: Column(
                    children: [
                      Expanded(
                        child: Image.asset('assets/images/vehicles/$code.png', fit: BoxFit.contain, excludeFromSemantics: true),
                      ),
                      const SizedBox(height: 4),
                      ExcludeSemantics(
                        child: Text(
                          label,
                          maxLines: 1,
                          style: TextStyle(
                            color: menuBlue,
                            fontSize: 12.5,
                            fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                if (selected)
                  const Positioned(right: 5, top: 5, child: Icon(Icons.check_circle_rounded, color: Mk.orange, size: 20)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
