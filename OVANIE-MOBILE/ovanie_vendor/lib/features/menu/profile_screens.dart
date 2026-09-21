import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/network/api_client.dart';
import '../../core/reference_data/reference_data_store.dart';
import 'menu_ui.dart';
import 'delivery_screens.dart';

class ShopProfileScreen extends StatefulWidget {
  const ShopProfileScreen({super.key});
  @override
  State<ShopProfileScreen> createState() => _ShopProfileState();
}

class _ShopProfileState extends State<ShopProfileScreen> {
  Map<String, dynamic> shop = {};
  bool loading = true;
  String? error;
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
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> edit() async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => ShopEditScreen(shop: shop)));
    if (mounted) await load();
  }

  @override
  Widget build(BuildContext context) {
    final p = mapOf(shop['presentation']);
    return MenuPage(
      title: 'Profil boutique',
      subtitle: 'Gérez les informations et la présentation de votre boutique',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [BoxShadow(color: Color(0x1A02173D), blurRadius: 18, offset: Offset(0, 8))],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  menuImage(shop['logo_url'], circle: true, size: 58),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          screenText(shop['name'], 'Ma boutique'),
                          style: const TextStyle(color: menuBlue, fontSize: 16.5, fontWeight: FontWeight.w800),
                        ),
                        Text(
                          screenText(shop['owner_name']),
                          style: const TextStyle(color: menuMuted, fontSize: 13),
                        ),
                        if (['approved', 'verified', 'validated'].contains(shop['kyc_status'])) ...[
                          const SizedBox(height: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(color: const Color(0xFFEAF8EC), borderRadius: BorderRadius.circular(8)),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.check_circle, color: orderGreen, size: 13),
                                SizedBox(width: 4),
                                Text('Vendeur vérifié', style: TextStyle(color: orderGreen, fontSize: 11.5, fontWeight: FontWeight.w700)),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  ElevatedButton.icon(
                    onPressed: edit,
                    icon: const Icon(Icons.edit, size: 16, color: Colors.white),
                    label: const Text('Modifier', style: TextStyle(color: Colors.white, fontSize: 12.5)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: menuOrange,
                      elevation: 0,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                    ),
                  ),
                ],
              ),
              if (screenText(shop['description'], '').isNotEmpty) ...[
                const SizedBox(height: 10),
                Text(
                  screenText(shop['description']),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: menuMuted, fontSize: 12.5, height: 1.35),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        DataCard(
          title: 'Informations générales',
          icon: Icons.storefront_outlined,
          child: Column(
            children: [
              dataLine('Nom de la boutique', screenText(shop['name'])),
              dataLine(
                'Catégorie principale',
                screenText(shop['main_category']),
              ),
              dataLine('Description', screenText(shop['description'])),
            ],
          ),
        ),
        DataCard(
          title: 'Logo & image',
          icon: Icons.image_outlined,
          child: Wrap(
            spacing: 12,
            runSpacing: 10,
            children: [
              menuImage(shop['logo_url'], size: 110),
              if (p['cover_url'] != null) menuImage(p['cover_url'], size: 110),
              const SizedBox(
                width: 200,
                child: Text(
                  'Format recommandé : JPG, PNG. Utilisez une image nette de votre boutique.',
                  style: TextStyle(color: menuMuted),
                ),
              ),
            ],
          ),
        ),
        DataCard(
          title: 'Contacts professionnels',
          icon: Icons.phone_outlined,
          child: Column(
            children: [
              dataLine(
                'Téléphone professionnel',
                screenText(p['business_phone']),
              ),
              dataLine('WhatsApp', screenText(shop['whatsapp'])),
              dataLine(
                'Email professionnel',
                screenText(shop['business_email']),
              ),
            ],
          ),
        ),
        DataCard(
          title: 'Livraison & logistique',
          icon: Icons.local_shipping_outlined,
          trailing: IconButton(
            onPressed: () =>
                openMenuPage(context, const DeliverySettingsScreen()),
            icon: const Icon(Icons.chevron_right),
          ),
          child: Column(
            children: [
              dataLine('Zones de livraison', screenText(shop['delivery_zone'])),
              dataLine(
                'Mode de livraison',
                screenText(shop['logistics_mode_label']),
              ),
              dataLine('Adresse', screenText(shop['address'])),
            ],
          ),
        ),
      ],
    );
  }
}

class ShopEditScreen extends StatefulWidget {
  const ShopEditScreen({super.key, required this.shop});
  final Map<String, dynamic> shop;
  @override
  State<ShopEditScreen> createState() => _ShopEditState();
}

class _ShopEditState extends State<ShopEditScreen> {
  final form = GlobalKey<FormState>();
  final fields = <String, TextEditingController>{};
  File? logo, cover;
  bool saving = false;
  String? category;
  Map<String, String> get categories {
    final remote = OvanieReferenceDataStore.instance.records('shop_categories');
    if (remote.isNotEmpty) {
      return <String, String>{
        for (final item in remote)
          if ('${item['slug'] ?? ''}'.trim().isNotEmpty)
            '${item['slug']}'.trim(): '${item['name'] ?? item['slug']}'.trim(),
      };
    }
    return const <String, String>{
      'ciment-beton': 'Ciment & béton',
      'fer-metaux': 'Fer & métaux',
      'carrelage': 'Carrelage',
      'peinture': 'Peinture',
      'isolation': 'Isolation',
      'electricite': 'Électricité',
      'plomberie': 'Plomberie',
      'bois': 'Bois',
      'outillage': 'Outillage',
      'solaire': 'Solaire',
      'quincaillerie': 'Quincaillerie',
    };
  }
  @override
  void initState() {
    super.initState();
    for (final key in [
      'name',
      'description',
      'business_phone',
      'business_email',
      'whatsapp',
    ]) {
      fields[key] = TextEditingController(
        text: screenText(
          key == 'business_phone'
              ? mapOf(widget.shop['presentation'])[key]
              : widget.shop[key],
          '',
        ),
      );
    }
    category = widget.shop['main_category'];
  }

  @override
  void dispose() {
    for (final c in fields.values) c.dispose();
    super.dispose();
  }

  Future<void> pick(bool isLogo) async {
    try {
      final f = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        maxWidth: 1800,
        imageQuality: 90,
      );
      if (f != null && mounted)
        setState(() {
          if (isLogo) {
            logo = File(f.path);
          } else {
            cover = File(f.path);
          }
        });
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  Widget field(
    String key,
    String label, {
    int lines = 1,
    bool required = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: TextFormField(
      controller: fields[key],
      maxLines: lines,
      maxLength: key == 'description' ? 700 : null,
      validator: (v) => required && (v ?? '').trim().isEmpty
          ? 'Champ obligatoire'
          : key == 'business_email' && (v ?? '').isNotEmpty && !v!.contains('@')
          ? 'Email invalide'
          : null,
      decoration: InputDecoration(
        labelText: label,
        alignLabelWithHint: true,
        border: const OutlineInputBorder(),
      ),
    ),
  );
  Future<void> save() async {
    if (!form.currentState!.validate()) return;
    setState(() => saving = true);
    try {
      await menuApi(
        'shop/presentation',
        method: 'POST',
        data: {
          for (final e in fields.entries) e.key: e.value.text.trim(),
          'main_category': category,
        },
        files: {
          if (logo != null) 'logo': logo!,
          if (cover != null) 'cover': cover!,
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
    title: 'Modifier le profil boutique',
    subtitle:
        'Mettez à jour les informations et la présentation de votre boutique',
    refresh: () async {},
    children: [
      ShopIdentity(shop: widget.shop),
      Form(
        key: form,
        child: Column(
          children: [
            DataCard(
              title: 'Informations générales',
              icon: Icons.info,
              child: Column(
                children: [
                  field('name', 'Nom de la boutique', required: true),
                  DropdownButtonFormField<String>(
                    initialValue: categories.containsKey(category)
                        ? category
                        : null,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Catégorie principale',
                      border: OutlineInputBorder(),
                    ),
                    items: categories.entries
                        .map(
                          (e) => DropdownMenuItem(
                            value: e.key,
                            child: Text(e.value),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => category = v,
                  ),
                  const SizedBox(height: 14),
                  field('description', 'Description de la boutique', lines: 4),
                ],
              ),
            ),
            DataCard(
              title: 'Logo & image',
              icon: Icons.image_outlined,
              child: Column(
                children: [
                  Wrap(
                    spacing: 12,
                    runSpacing: 10,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      logo == null
                          ? menuImage(widget.shop['logo_url'], size: 80)
                          : Image.file(
                              logo!,
                              width: 80,
                              height: 80,
                              fit: BoxFit.cover,
                            ),
                      menuButton(
                        'Changer le logo',
                        () => pick(true),
                        icon: Icons.photo_camera_outlined,
                        outlined: true,
                      ),
                    ],
                  ),
                  const Divider(),
                  Wrap(
                    spacing: 12,
                    runSpacing: 10,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      cover == null
                          ? menuImage(
                              mapOf(widget.shop['presentation'])['cover_url'],
                              size: 80,
                            )
                          : Image.file(
                              cover!,
                              width: 80,
                              height: 80,
                              fit: BoxFit.cover,
                            ),
                      menuButton(
                        'Modifier la couverture',
                        () => pick(false),
                        icon: Icons.image_outlined,
                        outlined: true,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            DataCard(
              title: 'Contacts professionnels',
              icon: Icons.phone,
              child: Column(
                children: [
                  field('business_phone', 'Téléphone professionnel'),
                  field('whatsapp', 'WhatsApp'),
                  field('business_email', 'Email professionnel'),
                ],
              ),
            ),
          ],
        ),
      ),
      menuInfo(
        'Les modifications seront visibles après enregistrement de votre profil boutique.',
      ),
      dataPair(
        menuButton(
          'Annuler',
          saving ? null : () => Navigator.pop(context),
          outlined: true,
        ),
        menuButton(
          saving ? 'Enregistrement…' : 'Enregistrer',
          saving ? null : save,
        ),
      ),
    ],
  );
}

class PersonalProfileScreen extends StatefulWidget {
  const PersonalProfileScreen({super.key});
  @override
  State<PersonalProfileScreen> createState() => _PersonalState();
}

class _PersonalState extends State<PersonalProfileScreen> {
  Map<String, dynamic> data = {};
  bool loading = true, busy = false;
  String? error;
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
      data = await menuApi('profile');
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> edit() async {
    final user = mapOf(data['user']);
    final fields = {
      for (final k in ['name', 'phone', 'email', 'whatsapp_phone', 'city'])
        k: TextEditingController(text: screenText(user[k], '')),
    };
    final key = GlobalKey<FormState>();
    final labels = {
      'name': 'Nom complet',
      'phone': 'Téléphone',
      'email': 'Email',
      'whatsapp_phone': 'WhatsApp',
      'city': 'Ville',
    };
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Modifier mon profil'),
        content: SingleChildScrollView(
          child: Form(
            key: key,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (final e in fields.entries)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: TextFormField(
                      controller: e.value,
                      decoration: InputDecoration(labelText: labels[e.key]),
                      validator: (v) =>
                          ['name', 'phone', 'email'].contains(e.key) &&
                              (v ?? '').trim().isEmpty
                          ? 'Champ obligatoire'
                          : null,
                    ),
                  ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () {
              if (key.currentState!.validate())
                Navigator.pop(ctx, {
                  for (final e in fields.entries) e.key: e.value.text.trim(),
                });
            },
            child: const Text('Enregistrer'),
          ),
        ],
      ),
    );
    if (values == null || !mounted) return;
    setState(() => busy = true);
    try {
      await menuApi('profile', method: 'PATCH', data: values);
      await load();
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = mapOf(data['user']);
    return MenuPage(
      title: 'Mon profil',
      subtitle: 'Consultez et mettez à jour vos informations personnelles',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [BoxShadow(color: Color(0x1A02173D), blurRadius: 18, offset: Offset(0, 8))],
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              menuImage(data['avatar_url'], circle: true, size: 58),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      screenText(u['name']),
                      style: const TextStyle(color: menuBlue, fontSize: 16.5, fontWeight: FontWeight.w800),
                    ),
                    if (['approved', 'verified', 'validated'].contains(u['status'])) ...[
                      const SizedBox(height: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(color: const Color(0xFFEAF8EC), borderRadius: BorderRadius.circular(8)),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.check_circle, color: orderGreen, size: 13),
                            SizedBox(width: 4),
                            Text('Vendeur vérifié', style: TextStyle(color: orderGreen, fontSize: 11.5, fontWeight: FontWeight.w700)),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 4),
                    const Text(
                      'Informations personnelles du titulaire du compte',
                      style: TextStyle(color: menuMuted, fontSize: 11.5),
                    ),
                  ],
                ),
              ),
              ElevatedButton.icon(
                onPressed: busy ? null : edit,
                icon: const Icon(Icons.edit, size: 16, color: Colors.white),
                label: const Text('Modifier', style: TextStyle(color: Colors.white, fontSize: 12.5)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: menuOrange,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        DataCard(
          title: 'Informations personnelles',
          icon: Icons.person_outline,
          child: Column(
            children: [
              for (final e in {
                'name': 'Nom complet',
                'phone': 'Téléphone',
                'email': 'Email',
                'whatsapp_phone': 'WhatsApp',
              }.entries)
                dataLine(e.value, screenText(u[e.key])),
              dataLine('Pays', screenText(mapOf(data['address'])['country'])),
            ],
          ),
        ),
        DataCard(
          title: 'Adresse',
          icon: Icons.location_on_outlined,
          child: Column(
            children: [
              dataLine('Ville', screenText(mapOf(data['address'])['city'] ?? u['city'])),
              dataLine('Commune', screenText(mapOf(data['address'])['commune'])),
              dataLine('Quartier', screenText(mapOf(data['address'])['quartier'])),
              dataLine('Adresse', screenText(mapOf(data['address'])['address'])),
            ],
          ),
        ),
        DataCard(
          title: 'Compte',
          icon: Icons.shield_outlined,
          child: Column(
            children: [
              dataLine('Rôle', screenText(u['role'])),
              dataLine('Date d’inscription', dateLabel(u['created_at'])),
              dataLine('Statut du compte', screenText(u['status'])),
            ],
          ),
        ),
      ],
    );
  }
}
