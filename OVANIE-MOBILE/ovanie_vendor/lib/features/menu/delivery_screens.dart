import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../../data/vendor_repository.dart';
import 'menu_ui.dart';
import 'logistics_settings_screen.dart';

class DeliverySettingsScreen extends StatefulWidget {
  const DeliverySettingsScreen({super.key});
  @override
  State<DeliverySettingsScreen> createState() => _DeliveryState();
}

class _DeliveryState extends State<DeliverySettingsScreen> {
  Map<String, dynamic> shop = {};
  bool loading = true, saving = false;
  String? error;
  String mode = 'ovanie', delay = '24_48h';
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
      mode = screenText(shop['logistics_type'], 'ovanie');
      delay = screenText(shop['processing_time'], '24_48h');
      final p = mapOf(mapOf(shop['presentation'])['preparation']);
      days = ((p['days'] as List?) ?? []).map((e) => number(e).toInt()).toSet();
      TimeOfDay? parse(dynamic v) {
        final s = '$v'.split(':');
        return s.length == 2
            ? TimeOfDay(hour: int.parse(s[0]), minute: int.parse(s[1]))
            : null;
      }

      start = parse(p['start']);
      end = parse(p['end']);
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String time(TimeOfDay? t) => t == null
      ? 'Choisir'
      : '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
  Future<void> pickTime(bool first) async {
    final t = await showTimePicker(
      context: context,
      initialTime: (first ? start : end) ?? const TimeOfDay(hour: 8, minute: 0),
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
    if (days.isEmpty || start == null || end == null) {
      menuError(
        context,
        Exception('Choisissez les jours et les horaires de préparation.'),
      );
      return;
    }
    setState(() => saving = true);
    try {
      await menuApi(
        'shop/preparation-settings',
        method: 'POST',
        data: {
          'processing_time': delay,
          'days': days.toList(),
          'start': time(start),
          'end': time(end),
        },
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Paramètres enregistrés.')),
        );
      }
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> editLogistics({bool change = false}) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => LogisticsSettingsScreen(
          shop: shop,
          targetMode: change ? (mode == 'seller' ? 'ovanie' : 'seller') : mode,
        ),
      ),
    );
    if (mounted) await load();
  }

  @override
  Widget build(BuildContext context) => MenuPage(
    title: 'Livraison & logistique',
    subtitle: 'Configurez votre mode logistique et vos délais de traitement',
    refresh: load,
    loading: loading,
    error: error,
    children: [
      ShopIdentity(shop: shop),
      DataCard(
        title: 'Mode logistique',
        icon: Icons.local_shipping_outlined,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            menuHeading(
              mode == 'seller' ? 'Ma propre logistique' : 'OVANIE Logistics',
            ),
            const SizedBox(height: 12),
            menuButton(
              'Changer de mode logistique',
              saving ? null : () => editLogistics(change: true),
            ),
            TextButton(
              onPressed: saving ? null : () => editLogistics(),
              child: const Text('Modifier mes informations logistiques'),
            ),
            const Text(
              'Les commandes déjà créées ou en cours conservent leur mode logistique.',
              style: TextStyle(color: menuMuted),
            ),
          ],
        ),
      ),
      DataCard(
        title: 'Délai de traitement',
        icon: Icons.schedule,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Sélectionnez le délai nécessaire avant la prise en charge de la commande.',
              style: TextStyle(color: menuMuted),
            ),
            Wrap(
              spacing: 8,
              children: delays.entries
                  .map(
                    (e) => ChoiceChip(
                      label: Text(e.value),
                      selected: delay == e.key,
                      onSelected: (_) => setState(() => delay = e.key),
                    ),
                  )
                  .toList(),
            ),
          ],
        ),
      ),
      DataCard(
        title: 'Disponibilité de préparation',
        icon: Icons.calendar_month_outlined,
        child: Column(
          children: [
            Wrap(
              spacing: 5,
              children: List.generate(
                7,
                (i) => FilterChip(
                  label: Text(dayNames[i]),
                  selected: days.contains(i + 1),
                  onSelected: (v) => setState(() {
                    if (v) {
                      days.add(i + 1);
                    } else {
                      days.remove(i + 1);
                    }
                  }),
                ),
              ),
            ),
            ListTile(
              title: const Text('Heure de début'),
              trailing: Text(time(start)),
              onTap: () => pickTime(true),
            ),
            ListTile(
              title: const Text('Heure limite'),
              trailing: Text(time(end)),
              onTap: () => pickTime(false),
            ),
          ],
        ),
      ),
      if (mode == 'seller')
        DataCard(
          title: 'Zones de livraison',
          icon: Icons.location_on_outlined,
          child: ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(screenText(shop['delivery_zone'], 'Gérer les zones')),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => openMenuPage(context, const DeliveryZonesScreen()),
          ),
        ),
      if (mode == 'seller')
        menuInfo(
          'Les capacités de transport et les tarifs par commune doivent être configurés pour la logistique vendeur.',
        ),
      menuButton(
        saving ? 'Enregistrement…' : 'Enregistrer les paramètres',
        saving ? null : save,
      ),
    ],
  );
}

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
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => DeliveryZoneEditScreen(shop: shop, zone: zone),
      ),
    );
    if (mounted) await load();
  }

  @override
  Widget build(BuildContext context) {
    final all = rowsOf(data['zones']);
    final rows = all
        .where(
          (z) =>
              '${z['commune']} ${z['city']} ${z['district']}'
                  .toLowerCase()
                  .contains(query.toLowerCase()) &&
              (filter == 0 ||
                  filter == 1 && z['is_active'] == true ||
                  filter == 2 && z['is_active'] != true),
        )
        .toList();
    return MenuPage(
      title: 'Zones de livraison',
      subtitle: 'Gérez les zones géographiques couvertes par votre boutique',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        ShopIdentity(shop: shop),
        MenuMetrics(
          items: [
            (
              'Zones actives',
              '${all.where((z) => z['is_active'] == true).length}',
              Icons.map_outlined,
              menuBlue,
            ),
            (
              'Zones suspendues',
              '${all.where((z) => z['is_active'] != true).length}',
              Icons.schedule,
              menuOrange,
            ),
          ],
        ),
        menuSearch((v) => setState(() => query = v), 'Rechercher une zone…'),
        menuFilters(
          ['Toutes', 'Actives', 'Suspendues'],
          filter,
          (v) => setState(() => filter = v),
        ),
        if (data['uses_seller_logistics'] == true || widget.configuring)
          Align(
            alignment: Alignment.centerRight,
            child: menuButton(
              'Ajouter une zone',
              () => edit(),
              icon: Icons.add,
            ),
          )
        else
          menuInfo(
            'OVANIE Logistics gère la couverture et les tarifs de livraison de votre boutique.',
          ),
        const SizedBox(height: 10),
        menuHeading('Liste des zones'),
        const SizedBox(height: 10),
        if (rows.isEmpty) menuEmpty('Aucune zone configurée'),
        for (final z in rows)
          DataCard(
            child: InkWell(
              onTap: () => edit(z),
              child: Row(
                children: [
                  menuIcon(Icons.location_on_outlined),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        menuHeading(screenText(z['commune'])),
                        Text(
                          '${screenText(z['city'])} • ${screenText(z['vehicle_code'])}',
                          style: const TextStyle(
                            color: menuMuted,
                            fontSize: 12,
                          ),
                        ),
                        Wrap(
                          spacing: 8,
                          runSpacing: 4,
                          children: [
                            menuPill(
                              z['is_active'] == true ? 'Active' : 'Suspendue',
                              color: z['is_active'] == true
                                  ? Colors.green
                                  : menuMuted,
                            ),
                            Text(screenText(z['estimated_delay'])),
                            Text(screenMoney(z['delivery_price'])),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: menuBlue),
                ],
              ),
            ),
          ),
        menuInfo(
          'Les zones informent les clients sur la couverture géographique, les délais et les frais de livraison.',
        ),
      ],
    );
  }
}

class DeliveryZoneEditScreen extends StatefulWidget {
  const DeliveryZoneEditScreen({super.key, required this.shop, this.zone});
  final Map<String, dynamic> shop;
  final Map<String, dynamic>? zone;
  @override
  State<DeliveryZoneEditScreen> createState() => _ZoneEditState();
}

class _ZoneEditState extends State<DeliveryZoneEditScreen> {
  final form = GlobalKey<FormState>();
  final price = TextEditingController(), delay = TextEditingController();
  List<Map<String, dynamic>> communes = [];
  int? commune;
  String? vehicle;
  bool active = true, loading = true, saving = false;
  String? error;
  @override
  void initState() {
    super.initState();
    final z = widget.zone ?? {};
    price.text = screenText(z['delivery_price'], '');
    delay.text = screenText(z['estimated_delay'], '');
    commune = z['commune_id'] == null ? null : number(z['commune_id']).toInt();
    vehicle = z['vehicle_code'];
    active = z['is_active'] != false;
    load();
  }

  @override
  void dispose() {
    price.dispose();
    delay.dispose();
    super.dispose();
  }

  Future<void> load() async {
    try {
      communes = rowsOf((await VendorRepository.instance.meta())['communes']);
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> save() async {
    if (!form.currentState!.validate()) return;
    setState(() => saving = true);
    try {
      await menuApi(
        'shop/delivery/zones${widget.zone == null ? '' : '/${widget.zone!['id']}'}',
        method: widget.zone == null ? 'POST' : 'PATCH',
        data: {
          'commune_id': commune,
          'vehicle_code': vehicle,
          'delivery_price': price.text.trim(),
          'estimated_delay': delay.text.trim(),
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

  @override
  Widget build(BuildContext context) => MenuPage(
    title: widget.zone == null ? 'Ajouter une zone' : 'Modifier une zone',
    subtitle: 'Définissez la couverture, les délais et les frais de livraison',
    refresh: load,
    loading: loading,
    error: error,
    children: [
      ShopIdentity(shop: widget.shop),
      DataCard(
        title: 'Informations de la zone',
        child: Form(
          key: form,
          child: Column(
            children: [
              DropdownButtonFormField<int>(
                initialValue:
                    communes.any((z) => number(z['id']).toInt() == commune)
                    ? commune
                    : null,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Commune couverte',
                  border: OutlineInputBorder(),
                ),
                items: communes
                    .map(
                      (z) => DropdownMenuItem(
                        value: number(z['id']).toInt(),
                        child: Text(screenText(z['name'])),
                      ),
                    )
                    .toList(),
                onChanged: (v) => setState(() => commune = v),
                validator: (v) => v == null ? 'Choisissez une commune' : null,
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                initialValue: vehicle,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Véhicule de livraison',
                  border: OutlineInputBorder(),
                ),
                items:
                    const {
                          'moto': 'Moto',
                          'tricycle': 'Tricycle',
                          'pickup': 'Pickup',
                          'camion_3t': 'Camion 3 t',
                          'camion_10t': 'Camion 10 t',
                        }.entries
                        .map(
                          (e) => DropdownMenuItem(
                            value: e.key,
                            child: Text(e.value),
                          ),
                        )
                        .toList(),
                onChanged: (v) => vehicle = v,
                validator: (v) => v == null ? 'Choisissez un véhicule' : null,
              ),
              const SizedBox(height: 14),
              dataPair(
                TextFormField(
                  controller: delay,
                  decoration: const InputDecoration(
                    labelText: 'Délai de livraison',
                    hintText: 'Ex. 24 h',
                    border: OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      (v ?? '').trim().isEmpty ? 'Champ obligatoire' : null,
                ),
                TextFormField(
                  controller: price,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Frais de livraison',
                    suffixText: 'FCFA',
                    border: OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      double.tryParse(v ?? '') == null || number(v) < 0
                      ? 'Montant invalide'
                      : null,
                ),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Zone active'),
                value: active,
                onChanged: (v) => setState(() => active = v),
              ),
            ],
          ),
        ),
      ),
      DataCard(
        title: 'Couverture géographique',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              communes
                  .where((c) => number(c['id']).toInt() == commune)
                  .map((c) => screenText(c['name']))
                  .join(', '),
              style: const TextStyle(color: menuBlue),
            ),
            menuButton(
              'Voir la commune sur la carte',
              commune == null
                  ? null
                  : () async {
                      final name = communes.firstWhere(
                        (z) => number(z['id']).toInt() == commune,
                      )['name'];
                      try {
                        await launchUrl(
                          Uri.https('www.google.com', '/maps/search/', {
                            'api': '1',
                            'query': '$name, Abidjan, Côte d’Ivoire',
                          }),
                        );
                      } catch (e) {
                        if (context.mounted) menuError(context, e);
                      }
                    },
              icon: Icons.map_outlined,
              outlined: true,
            ),
          ],
        ),
      ),
      menuInfo(
        'Un tarif est enregistré par commune et par type de véhicule pour calculer les frais de livraison des commandes.',
      ),
      dataPair(
        menuButton(
          'Annuler',
          saving ? null : () => Navigator.pop(context),
          outlined: true,
        ),
        menuButton(
          saving ? 'Enregistrement…' : 'Enregistrer la zone',
          saving ? null : save,
        ),
      ),
    ],
  );
}
