import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/reference_data/reference_data_store.dart';
import 'delivery_screens.dart';
import 'menu_ui.dart';

/// Catégories de boutique : référentiel du serveur, à défaut la liste de base.
Map<String, String> shopCategories() {
  final remote = OvanieReferenceDataStore.instance.records('shop_categories');
  if (remote.isNotEmpty) {
    return <String, String>{
      for (final item in remote)
        if ('${item['slug'] ?? ''}'.trim().isNotEmpty) '${item['slug']}'.trim(): '${item['name'] ?? item['slug']}'.trim(),
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

/// Libellé d'une catégorie ; une valeur inconnue du référentiel n'est pas affichée telle quelle.
String shopCategoryLabel(dynamic slug) {
  final key = '${slug ?? ''}'.trim();
  if (key.isEmpty) return 'Non renseignée';
  return shopCategories()[key] ?? 'Non renseignée';
}

String _capitalized(String text) => text.isEmpty ? text : text[0].toUpperCase() + text.substring(1);

/// Carte d'en-tête : couverture, logo, nom et bouton d'action.
class _ShopHero extends StatelessWidget {
  const _ShopHero({required this.shop, this.action});
  final Map<String, dynamic> shop;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final cover = screenText(mapOf(shop['presentation'])['cover_url'], '');
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              AspectRatio(aspectRatio: 5 / 2, child: ShopCoverImage(url: cover)),
              Positioned(
                left: 14,
                bottom: -28,
                child: Container(
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                  child: menuImage(shop['logo_url'], circle: true, size: 60),
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 34, 14, 14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        screenText(shop['name'], 'Ma boutique'),
                        style: const TextStyle(color: menuBlue, fontSize: 19, height: 1.2, fontWeight: FontWeight.w800),
                      ),
                      if (menuShopVerified(shop)) ...[const SizedBox(height: 6), menuVerifiedPill()],
                    ],
                  ),
                ),
                if (action != null) ...[const SizedBox(width: 10), action!],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

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
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => ShopEditScreen(shop: shop)));
    if (!mounted) return;
    if (saved == true) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Profil de la boutique enregistré.')));
    }
    await load();
  }

  Future<void> delivery() async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const DeliverySettingsScreen()));
    if (mounted) await load();
  }

  @override
  Widget build(BuildContext context) {
    final p = mapOf(shop['presentation']);
    final cover = screenText(p['cover_url'], '');
    final description = screenText(shop['description'], '');
    return MenuPage(
      title: 'Profil boutique',
      subtitle: 'Gérez les informations et la présentation de votre boutique',
      refresh: load,
      loading: loading,
      error: error,
      hasContent: shop.isNotEmpty,
      children: [
        _ShopHero(shop: shop, action: menuEditButton(edit)),
        DataCard(
          title: 'Informations générales',
          icon: Icons.storefront_outlined,
          iconColor: menuOrange,
          child: Column(
            children: [
              dataLine('Nom de la boutique', screenText(shop['name'])),
              const Divider(height: 1, color: orderBorder),
              dataLine('Catégorie principale', shopCategoryLabel(shop['main_category'])),
              const Divider(height: 1, color: orderBorder),
              dataLine('Description', description.isEmpty ? 'Non renseignée' : description),
            ],
          ),
        ),
        DataCard(
          title: 'Logo & image',
          icon: Icons.image_outlined,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      children: [
                        Container(
                          height: 92,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: orderBorder),
                          ),
                          child: menuImage(shop['logo_url'], circle: true, size: 66),
                        ),
                        const SizedBox(height: 6),
                        const Text('Logo de la boutique', style: TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500)),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: SizedBox(
                            height: 92,
                            width: double.infinity,
                            child: cover.isEmpty
                                ? const ColoredBox(
                                    color: Mk.softBlue,
                                    child: Icon(Icons.add_photo_alternate_outlined, color: Mk.icon, size: 30),
                                  )
                                : ShopCoverImage(url: cover),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          cover.isEmpty ? 'Aucune image de couverture' : 'Image de couverture',
                          style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              const Text(
                'Format recommandé : JPG, PNG. Utilisez une image nette de votre boutique.',
                style: TextStyle(color: menuMuted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
        DataCard(
          title: 'Contacts professionnels',
          icon: Icons.call_rounded,
          iconColor: menuGreen,
          child: Column(
            children: [
              dataLine('Téléphone professionnel', screenText(menuPhone(p['business_phone']), 'Non renseigné'), icon: Icons.call_outlined),
              const Divider(height: 1, color: orderBorder),
              dataLine('WhatsApp', screenText(menuPhone(shop['whatsapp']), 'Non renseigné'), icon: Icons.chat_outlined),
              const Divider(height: 1, color: orderBorder),
              dataLine('Email professionnel', screenText(shop['business_email'], 'Non renseigné'), icon: Icons.mail_outline_rounded),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: delivery,
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  children: [
                    Row(
                      children: [
                        menuIcon(Icons.local_shipping_outlined, size: 38),
                        const SizedBox(width: 11),
                        Expanded(child: menuHeading('Livraison & logistique')),
                        const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
                      ],
                    ),
                    const SizedBox(height: 10),
                    dataLine(
                      'Zone de livraison',
                      screenText(_capitalized(screenText(shop['delivery_zone'], '')), 'Non renseignée'),
                      icon: Icons.location_on_outlined,
                    ),
                    const Divider(height: 1, color: orderBorder),
                    dataLine(
                      'Mode de livraison',
                      screenText(shop['logistics_mode_label'], 'À configurer'),
                      icon: Icons.inventory_2_outlined,
                    ),
                    const Divider(height: 1, color: orderBorder),
                    dataLine('Adresse', screenText(shop['address'], 'Non renseignée'), icon: Icons.map_outlined),
                  ],
                ),
              ),
            ),
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

  @override
  void initState() {
    super.initState();
    for (final key in ['name', 'description', 'business_phone', 'business_email', 'whatsapp']) {
      fields[key] = TextEditingController(
        text: screenText(key == 'business_phone' ? mapOf(widget.shop['presentation'])[key] : widget.shop[key], ''),
      );
    }
    final current = '${widget.shop['main_category'] ?? ''}';
    category = shopCategories().containsKey(current) ? current : null;
    fields['description']!.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> pick(bool isLogo) async {
    try {
      final f = await ImagePicker().pickImage(source: ImageSource.gallery, maxWidth: 1800, imageQuality: 90);
      if (f != null && mounted) {
        setState(() {
          if (isLogo) {
            logo = File(f.path);
          } else {
            cover = File(f.path);
          }
        });
      }
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  Future<void> pickCategory() async {
    final picked = await menuPick<String>(context, 'Catégorie principale', shopCategories(), category);
    if (picked != null && mounted) setState(() => category = picked);
  }

  Future<void> save() async {
    if (!form.currentState!.validate()) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Corrigez les champs signalés en rouge.')));
      return;
    }
    setState(() => saving = true);
    try {
      await menuApi(
        'shop/presentation',
        method: 'POST',
        data: {
          for (final e in fields.entries) e.key: e.value.text.trim(),
          if (category != null) 'main_category': category,
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

  String? _phone(String? v) {
    final text = (v ?? '').trim();
    if (text.isEmpty) return null;
    final digits = text.replaceAll(RegExp(r'\D'), '');
    final local = digits.length == 13 && digits.startsWith('225') ? digits.substring(3) : digits;
    return local.length == 10 ? null : 'Le numéro doit contenir 10 chiffres.';
  }

  @override
  Widget build(BuildContext context) {
    final categories = shopCategories();
    final coverUrl = screenText(mapOf(widget.shop['presentation'])['cover_url'], '');
    return MenuPage(
      title: 'Modifier le profil boutique',
      subtitle: 'Mettez à jour les informations et la présentation de votre boutique',
      refresh: () async {},
      actions: [
        MkBarButton(label: 'Annuler', onTap: saving ? null : () => Navigator.pop(context)),
        MkBarButton(label: 'Enregistrer', filled: true, loading: saving, onTap: saving ? null : save),
      ],
      children: [
        Form(
          key: form,
          child: Column(
            children: [
              // ---- Logo & image ---------------------------------------------------
              Container(
                margin: const EdgeInsets.only(bottom: 10),
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Wrap(
                      alignment: WrapAlignment.spaceBetween,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            menuIcon(Icons.image_outlined, size: 38),
                            const SizedBox(width: 11),
                            menuHeading('Logo & image'),
                          ],
                        ),
                        OutlinedButton.icon(
                          onPressed: saving ? null : () => pick(false),
                          icon: const Icon(Icons.image_outlined, size: 18),
                          label: const Text('Modifier la couverture'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: orderBlue,
                            side: const BorderSide(color: Mk.border),
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Stack(
                      clipBehavior: Clip.none,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: AspectRatio(
                            aspectRatio: 5 / 2,
                            child: cover != null ? Image.file(cover!, fit: BoxFit.cover) : ShopCoverImage(url: coverUrl),
                          ),
                        ),
                        Positioned(
                          left: 12,
                          bottom: -22,
                          child: Semantics(
                            button: true,
                            label: 'Changer le logo de la boutique',
                            child: GestureDetector(
                              onTap: saving ? null : () => pick(true),
                              child: Stack(
                                clipBehavior: Clip.none,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(3),
                                    decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                                    child: logo != null
                                        ? ClipOval(child: Image.file(logo!, width: 64, height: 64, fit: BoxFit.cover))
                                        : menuImage(widget.shop['logo_url'], circle: true, size: 64),
                                  ),
                                  Positioned(
                                    right: -2,
                                    bottom: -2,
                                    child: Container(
                                      width: 28,
                                      height: 28,
                                      decoration: BoxDecoration(
                                        color: menuAzure,
                                        shape: BoxShape.circle,
                                        border: Border.all(color: Colors.white, width: 2),
                                      ),
                                      child: const Icon(Icons.photo_camera_rounded, color: Colors.white, size: 15),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 30),
                    const Text(
                      'Touchez le logo pour le remplacer. Format recommandé : JPG ou PNG.',
                      style: TextStyle(color: menuMuted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),

              // ---- Informations générales ------------------------------------------
              DataCard(
                title: 'Informations générales',
                icon: Icons.description_outlined,
                iconColor: menuOrange,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const MkLabel('Nom de la boutique', required: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: fields['name'],
                      enabled: !saving,
                      textCapitalization: TextCapitalization.words,
                      inputFormatters: [LengthLimitingTextInputFormatter(120)],
                      style: mkFieldText,
                      decoration: mkInput(hint: 'Nom affiché aux clients'),
                      validator: (v) => (v ?? '').trim().length < 2 ? 'Indiquez le nom de votre boutique.' : null,
                    ),
                    const SizedBox(height: 14),
                    const MkLabel('Catégorie principale'),
                    const SizedBox(height: 6),
                    MenuSelect(
                      value: categories[category],
                      hint: 'Choisir une catégorie',
                      onTap: saving ? null : pickCategory,
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final entry in categories.entries.take(12))
                          SizedBox(
                            height: 36,
                            child: menuChip(
                              entry.value,
                              category == entry.key,
                              () => setState(() => category = entry.key),
                              activeColor: Mk.orange,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    const MkLabel('Description de la boutique'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: fields['description'],
                      enabled: !saving,
                      minLines: 4,
                      maxLines: 7,
                      inputFormatters: [LengthLimitingTextInputFormatter(700)],
                      style: mkFieldText.copyWith(height: 1.45),
                      decoration: mkInput(hint: 'Présentez vos produits et vos services'),
                    ),
                    const SizedBox(height: 4),
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        '${fields['description']!.text.characters.length}/700',
                        style: const TextStyle(color: menuMuted, fontSize: 12, fontWeight: FontWeight.w500),
                      ),
                    ),
                  ],
                ),
              ),

              // ---- Contacts professionnels -----------------------------------------
              DataCard(
                title: 'Contacts professionnels',
                icon: Icons.call_rounded,
                iconColor: menuGreen,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const MkLabel('Téléphone professionnel'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: fields['business_phone'],
                      enabled: !saving,
                      keyboardType: TextInputType.phone,
                      inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+ ]')), LengthLimitingTextInputFormatter(20)],
                      style: mkFieldText,
                      decoration: mkInput(hint: '07 00 00 00 00', icon: Icons.call_outlined),
                      validator: _phone,
                    ),
                    const SizedBox(height: 12),
                    const MkLabel('WhatsApp'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: fields['whatsapp'],
                      enabled: !saving,
                      keyboardType: TextInputType.phone,
                      inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+ ]')), LengthLimitingTextInputFormatter(20)],
                      style: mkFieldText,
                      decoration: mkInput(hint: '07 00 00 00 00', icon: Icons.chat_outlined),
                      validator: _phone,
                    ),
                    const SizedBox(height: 12),
                    const MkLabel('Email professionnel'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: fields['business_email'],
                      enabled: !saving,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      style: mkFieldText,
                      decoration: mkInput(hint: 'contact@maboutique.ci', icon: Icons.mail_outline_rounded),
                      validator: (v) {
                        final text = (v ?? '').trim();
                        if (text.isEmpty) return null;
                        return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(text) ? null : 'Adresse e-mail invalide.';
                      },
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        menuInfo('Les modifications seront visibles après enregistrement de votre profil boutique.'),
      ],
    );
  }
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
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _ProfileEditDialog(user: mapOf(data['user'])),
    );
    if (values == null || !mounted) return;
    setState(() => busy = true);
    try {
      await menuApi('profile', method: 'PATCH', data: values);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Votre profil a été mis à jour.')));
      }
      await load();
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return (parts.first[0] + (parts.length > 1 ? parts.last[0] : '')).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final u = mapOf(data['user']);
    final address = mapOf(data['address']);
    final shop = mapOf(data['shop']);
    final name = screenText(u['name'], '');
    final status = '${u['status'] ?? ''}';
    final statusColor = status == 'active' ? menuGreen : (status == 'suspended' ? menuRed : const Color(0xFFE85400));
    String value(dynamic raw, [String empty = 'Non renseigné']) => screenText(raw, empty);

    return MenuPage(
      title: 'Mon profil',
      subtitle: 'Consultez et mettez à jour vos informations personnelles',
      refresh: load,
      loading: loading || busy,
      error: error,
      hasContent: data.isNotEmpty,
      children: [
        Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              screenText(u['avatar_url'], '').isNotEmpty
                  ? menuImage(u['avatar_url'], circle: true, size: 64)
                  : Container(
                      width: 64,
                      height: 64,
                      alignment: Alignment.center,
                      decoration: const BoxDecoration(color: Mk.navy, shape: BoxShape.circle),
                      child: Text(
                        _initials(name),
                        style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800),
                      ),
                    ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name.isEmpty ? 'Mon compte' : name,
                      style: const TextStyle(color: menuBlue, fontSize: 19, height: 1.2, fontWeight: FontWeight.w800),
                    ),
                    if (menuShopVerified(shop)) ...[const SizedBox(height: 6), menuVerifiedPill()],
                    const SizedBox(height: 6),
                    const Text(
                      'Informations personnelles du titulaire du compte',
                      style: TextStyle(color: menuMuted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              menuEditButton(busy ? null : edit),
            ],
          ),
        ),
        DataCard(
          title: 'Informations personnelles',
          icon: Icons.person_outline_rounded,
          iconColor: menuOrange,
          child: Column(
            children: [
              dataLine('Nom complet', value(u['name'])),
              const Divider(height: 1, color: orderBorder),
              dataLine('Téléphone', value(menuPhone(u['phone']))),
              const Divider(height: 1, color: orderBorder),
              dataLine('Email', value(u['email'])),
              const Divider(height: 1, color: orderBorder),
              dataLine('WhatsApp', value(menuPhone(u['whatsapp_phone']))),
            ],
          ),
        ),
        DataCard(
          title: 'Adresse',
          icon: Icons.location_on_rounded,
          child: Column(
            children: [
              dataLine('Pays', value(address['country'])),
              const Divider(height: 1, color: orderBorder),
              dataLine('Ville', value(address['city'] ?? u['city'], 'Non renseignée')),
              const Divider(height: 1, color: orderBorder),
              dataLine('Commune', value(address['commune'], 'Non renseignée')),
              const Divider(height: 1, color: orderBorder),
              dataLine('Quartier', value(address['quartier'])),
              const Divider(height: 1, color: orderBorder),
              dataLine('Adresse', value(address['address'], 'Non renseignée')),
            ],
          ),
        ),
        DataCard(
          title: 'Compte',
          icon: Icons.shield_outlined,
          iconColor: menuOrange,
          child: Column(
            children: [
              dataLine('Rôle', const {'vendor': 'Vendeur', 'admin': 'Administrateur'}['${u['role']}'] ?? value(u['role'])),
              const Divider(height: 1, color: orderBorder),
              dataLine('Date d’inscription', value(menuDate(u['created_at']))),
              const Divider(height: 1, color: orderBorder),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    const Expanded(
                      flex: 5,
                      child: Text('Statut du compte', style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500)),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      flex: 6,
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: menuPill(
                          const {'active': 'Actif', 'suspended': 'Suspendu', 'pending': 'En attente'}[status] ?? value(u['status']),
                          color: statusColor,
                          icon: status == 'active' ? Icons.check_circle_rounded : Icons.info_rounded,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Fenêtre « Modifier mon profil » : nom, téléphone, e-mail, WhatsApp et ville.
class _ProfileEditDialog extends StatefulWidget {
  const _ProfileEditDialog({required this.user});
  final Map<String, dynamic> user;
  @override
  State<_ProfileEditDialog> createState() => _ProfileEditDialogState();
}

class _ProfileEditDialogState extends State<_ProfileEditDialog> {
  final _form = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields = {
    for (final key in ['name', 'phone', 'email', 'whatsapp_phone', 'city'])
      key: TextEditingController(text: screenText(widget.user[key], '')),
  };

  @override
  void dispose() {
    for (final field in _fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  String? _phone(String? v, {required bool required}) {
    final text = (v ?? '').trim();
    if (text.isEmpty) return required ? 'Champ obligatoire' : null;
    final digits = text.replaceAll(RegExp(r'\D'), '');
    final local = digits.length == 13 && digits.startsWith('225') ? digits.substring(3) : digits;
    return local.length == 10 ? null : 'Le numéro doit contenir 10 chiffres.';
  }

  Widget _field(String key, String label, {TextInputType? keyboard, String? Function(String?)? validator, bool phone = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          MkLabel(label),
          const SizedBox(height: 6),
          TextFormField(
            controller: _fields[key],
            keyboardType: keyboard,
            autocorrect: keyboard != TextInputType.emailAddress,
            textCapitalization: keyboard == null ? TextCapitalization.words : TextCapitalization.none,
            inputFormatters: phone
                ? [FilteringTextInputFormatter.allow(RegExp(r'[0-9+ ]')), LengthLimitingTextInputFormatter(20)]
                : [LengthLimitingTextInputFormatter(190)],
            style: mkFieldText,
            decoration: mkInput(hint: label),
            validator: validator,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MkTheme(
      lightStatusBar: false,
      child: Dialog(
        backgroundColor: Colors.white,
        insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 460),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(18, 20, 18, 16),
            child: Form(
              key: _form,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('Modifier mon profil', style: TextStyle(color: menuBlue, fontSize: 20, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 14),
                  _field('name', 'Nom complet', validator: (v) => (v ?? '').trim().length < 2 ? 'Champ obligatoire' : null),
                  _field('phone', 'Téléphone',
                      keyboard: TextInputType.phone, phone: true, validator: (v) => _phone(v, required: true)),
                  _field(
                    'email',
                    'Email',
                    keyboard: TextInputType.emailAddress,
                    validator: (v) =>
                        RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch((v ?? '').trim()) ? null : 'Adresse e-mail invalide.',
                  ),
                  _field('whatsapp_phone', 'WhatsApp',
                      keyboard: TextInputType.phone, phone: true, validator: (v) => _phone(v, required: false)),
                  _field('city', 'Ville'),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(child: MkBarButton(label: 'Annuler', color: Mk.icon, onTap: () => Navigator.pop(context))),
                      const SizedBox(width: 10),
                      Expanded(
                        child: MkBarButton(
                          label: 'Enregistrer',
                          filled: true,
                          onTap: () {
                            if (_form.currentState!.validate()) {
                              Navigator.pop(context, {for (final e in _fields.entries) e.key: e.value.text.trim()});
                            }
                          },
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
