import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../shell/vendor_tabs.dart';
import 'product_ui.dart';

const _line = Color(0xFFE4E9F1);
const _blue = Color(0xFF1456D9);
const _maxImages = 10;

/// Gestion des images d'un produit : image principale, galerie, ajout,
/// remplacement et suppression. Chaque action est enregistrée aussitôt.
class ProductImagesScreen extends StatefulWidget {
  const ProductImagesScreen({super.key, required this.productId});
  final int productId;

  @override
  State<ProductImagesScreen> createState() => _ProductImagesScreenState();
}

class _ProductImagesScreenState extends State<ProductImagesScreen> {
  Map<String, dynamic>? _product;
  List<Map<String, dynamic>> _images = [];
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await VendorRepository.instance.product(widget.productId, forceRefresh: true);
      final p = data['product'] is Map ? Map<String, dynamic>.from(data['product'] as Map) : <String, dynamic>{};
      final images = ((p['images'] as List?) ?? const []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      images.sort((a, b) => (int.tryParse('${a['sort_order'] ?? 0}') ?? 0).compareTo(int.tryParse('${b['sort_order'] ?? 0}') ?? 0));
      if (!mounted) return;
      setState(() {
        _product = p;
        _images = images;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  static bool _isMain(Map<String, dynamic> image) => image['is_main'] == true || '${image['is_main']}' == '1';

  Map<String, dynamic>? get _mainImage {
    for (final image in _images) {
      if (_isMain(image)) return image;
    }
    return _images.isEmpty ? null : _images.first;
  }

  Future<ImageSource?> _sourceSheet(String title) async {
    return showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => MkTheme(
        lightStatusBar: false,
        child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
          ),
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 12),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 44,
                    height: 5,
                    decoration: BoxDecoration(color: const Color(0xFFC9CED8), borderRadius: BorderRadius.circular(3)),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    title,
                    style: const TextStyle(color: Mk.navy, fontSize: 19, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  _SourceRow(
                    icon: Icons.image_outlined,
                    color: _blue,
                    label: 'Importer depuis la galerie',
                    onTap: () => Navigator.pop(sheetContext, ImageSource.gallery),
                  ),
                  const Divider(height: 1, color: _line),
                  _SourceRow(
                    icon: Icons.photo_camera_outlined,
                    color: Mk.orange,
                    label: 'Prendre une photo',
                    onTap: () => Navigator.pop(sheetContext, ImageSource.camera),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<File?> _pick(String title) async {
    final source = await _sourceSheet(title);
    if (source == null) return null;
    try {
      final picked = await ImagePicker().pickImage(source: source, imageQuality: 88, maxWidth: 1800, maxHeight: 1800);
      return picked == null ? null : File(picked.path);
    } catch (_) {
      _snack('Impossible d’ouvrir la photo. Vérifiez les autorisations de l’application.');
      return null;
    }
  }

  void _snack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  /// Exécute une modification, recharge la galerie et confirme par un message.
  Future<void> _run(Future<void> Function() action, String done) async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await action();
      await _load();
      _snack(done);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _add({bool main = false}) async {
    if (_images.length >= _maxImages) {
      _snack('Maximum $_maxImages images par produit.');
      return;
    }
    final file = await _pick('Ajouter une image');
    if (file == null) return;
    await _run(() => VendorRepository.instance.addProductImage(widget.productId, file, isMain: main || _images.isEmpty), 'Image ajoutée.');
  }

  Future<void> _replace(Map<String, dynamic> image) async {
    final file = await _pick('Remplacer l’image');
    if (file == null) return;
    await _run(() => VendorRepository.instance.replaceProductImage(int.parse('${image['id']}'), file), 'Image remplacée.');
  }

  Future<void> _setMain(Map<String, dynamic> image) =>
      _run(() => VendorRepository.instance.setMainProductImage(int.parse('${image['id']}')), 'Image principale mise à jour.');

  Future<void> _delete(Map<String, dynamic> image) async {
    if (_images.length <= 1) {
      _snack('Un produit doit garder au moins une image. Ajoutez-en une autre avant de supprimer celle-ci.');
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Supprimer cette image ?'),
        content: const Text('L’image sera retirée définitivement de la galerie produit.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Annuler')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Mk.danger),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    await _run(() => VendorRepository.instance.deleteProductImage(int.parse('${image['id']}')), 'Image supprimée.');
  }

  /// Le recadrage est fait par OVANIE à l'import : cette feuille l'explique et propose de remplacer la photo.
  Future<void> _showCropInfo(Map<String, dynamic> image) async {
    final replace = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => MkTheme(
        lightStatusBar: false,
        child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
          ),
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 44,
                      height: 5,
                      decoration: BoxDecoration(color: const Color(0xFFC9CED8), borderRadius: BorderRadius.circular(3)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Icon(Icons.crop_rounded, color: Mk.orange, size: 36),
                  const SizedBox(height: 8),
                  const Text(
                    'Recadrage automatique',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Mk.navy, fontSize: 19, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'OVANIE recadre chaque image au format carré et la pose sur un fond clair au moment de l’import. '
                    'Pour changer le cadrage, remplacez l’image par une photo mieux centrée.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.45, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 16),
                  MkBarButton(
                    label: 'Remplacer l’image',
                    icon: Icons.swap_horiz_rounded,
                    filled: true,
                    onTap: () => Navigator.pop(sheetContext, true),
                  ),
                  const SizedBox(height: 8),
                  MkBarButton(label: 'Fermer', color: Mk.icon, onTap: () => Navigator.pop(sheetContext, false)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    if (replace == true) await _replace(image);
  }

  void _preview(Map<String, dynamic> image) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        insetPadding: const EdgeInsets.all(18),
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: AspectRatio(
          aspectRatio: 1,
          child: Stack(
            children: [
              Positioned.fill(
                child: InteractiveViewer(
                  child: ProductNetworkImage(url: image['url'], fit: BoxFit.contain, borderRadius: 0),
                ),
              ),
              Positioned(
                top: 6,
                right: 6,
                child: IconButton.filled(
                  tooltip: 'Fermer',
                  style: IconButton.styleFrom(backgroundColor: Mk.navy),
                  onPressed: () => Navigator.pop(dialogContext),
                  icon: const Icon(Icons.close_rounded),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final product = _product;
    return MkTheme(
      lightStatusBar: false,
      child: Scaffold(
        backgroundColor: const Color(0xFFF5F7FB),
        body: Column(
          children: [
            const MkPageHeader(
              title: 'Gestion des images produit',
              subtitle: 'Ajoutez, organisez et optimisez les visuels de votre produit',
              showLogo: true,
            ),
            if (_saving) const LinearProgressIndicator(minHeight: 2, color: Mk.orange, backgroundColor: Color(0xFFFFEEE5)),
            Expanded(
              child: _loading && product == null
                  ? const Center(child: CircularProgressIndicator(color: Mk.orange))
                  : product == null
                  ? Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          MkNotice(_error ?? 'Produit introuvable.'),
                          const SizedBox(height: 14),
                          MkBarButton(label: 'Réessayer', filled: true, onTap: _load),
                        ],
                      ),
                    )
                  : ListView(
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
                      children: [
                        if (_error != null) ...[MkNotice(_error!), const SizedBox(height: 10)],
                        _ProductIdentity(product: product),
                        const SizedBox(height: 10),
                        _Section(number: '1', title: 'Image principale', child: _mainImageBlock()),
                        const SizedBox(height: 10),
                        _Section(
                          number: '2',
                          title: 'Galerie produit',
                          subtitle: 'Touchez l’étoile pour définir l’image principale',
                          trailing: '${_images.length}/$_maxImages',
                          child: _galleryGrid(),
                        ),
                        const SizedBox(height: 10),
                        const _Section(
                          number: '3',
                          title: 'Qualité et format',
                          child: _CheckList([
                            'Format recommandé : JPG ou PNG',
                            'Ratio conseillé : carré 1:1',
                            'Taille max : 5 Mo par image',
                            'Ajoutez plusieurs angles pour rassurer les acheteurs',
                            'Évitez les images floues ou avec texte',
                          ]),
                        ),
                        const SizedBox(height: 10),
                        const _Section(
                          number: '4',
                          title: 'Optimisation automatique',
                          subtitle: 'Appliquée par OVANIE à chaque image importée',
                          child: _AutoList(['Normaliser au format carré 1:1', 'Fond clair automatique', 'Compression optimisée web']),
                        ),
                      ],
                    ),
            ),
          ],
        ),
        bottomNavigationBar: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (product != null)
              MkActionBar(
                children: [
                  MkBarButton(label: 'Ajouter une photo', icon: Icons.add_photo_alternate_outlined, onTap: _saving ? null : _add),
                  MkBarButton(
                    label: 'Valider les images',
                    icon: Icons.check_rounded,
                    filled: true,
                    onTap: _saving
                        ? null
                        : () {
                            if (_images.isEmpty) {
                              _snack('Ajoutez au moins une image avant de valider.');
                              return;
                            }
                            Navigator.pop(context, true);
                          },
                  ),
                ],
              ),
            const VendorBottomBar(selected: VendorTab.products),
          ],
        ),
      ),
    );
  }

  Widget _mainImageBlock() {
    final main = _mainImage;
    if (main == null) {
      return _AddTile(label: 'Ajouter l’image principale', height: 150, onTap: _saving ? null : () => _add(main: true));
    }
    return Column(
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 230),
            child: AspectRatio(
              aspectRatio: 1,
              child: Stack(
                children: [
                  Positioned.fill(
                    child: Container(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: _line, width: 1.2),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: ProductNetworkImage(url: main['url'], fit: BoxFit.contain, borderRadius: 9),
                    ),
                  ),
                  Positioned(
                    left: 0,
                    top: 0,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(color: _blue, borderRadius: BorderRadius.circular(99)),
                      child: const Text(
                        'Image principale',
                        style: TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: _line, width: 1.2),
          ),
          child: IntrinsicHeight(
            child: Row(
              children: [
                Expanded(
                  child: _MainAction(icon: Icons.swap_horiz_rounded, label: 'Remplacer', onTap: _saving ? null : () => _replace(main)),
                ),
                const VerticalDivider(width: 1, indent: 10, endIndent: 10, color: _line),
                Expanded(
                  child: _MainAction(icon: Icons.crop_rounded, label: 'Recadrer', onTap: _saving ? null : () => _showCropInfo(main)),
                ),
                const VerticalDivider(width: 1, indent: 10, endIndent: 10, color: _line),
                Expanded(
                  child: _MainAction(icon: Icons.visibility_outlined, label: 'Aperçu', onTap: () => _preview(main)),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _galleryGrid() {
    final tiles = <Widget>[
      for (var i = 0; i < _images.length; i++)
        _GalleryTile(
          index: i,
          image: _images[i],
          main: identical(_images[i], _mainImage),
          saving: _saving,
          onPreview: () => _preview(_images[i]),
          onDelete: () => _delete(_images[i]),
          onSetMain: () => _setMain(_images[i]),
        ),
      if (_images.length < _maxImages) _AddTile(label: 'Ajouter des photos', onTap: _saving ? null : _add),
    ];

    return LayoutBuilder(
      builder: (context, c) {
        const gap = 8.0;
        final width = (c.maxWidth - gap * 2) / 3;
        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [for (final tile in tiles) SizedBox(width: width, height: width, child: tile)],
        );
      },
    );
  }
}

/// Carte blanche numérotée d'une section.
class _Section extends StatelessWidget {
  const _Section({required this.number, required this.title, required this.child, this.subtitle, this.trailing});

  final String number;
  final String title;
  final String? subtitle;
  final String? trailing;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 30,
                height: 30,
                alignment: Alignment.center,
                decoration: const BoxDecoration(color: _blue, shape: BoxShape.circle),
                child: Text(
                  number,
                  style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        title,
                        style: const TextStyle(color: Mk.navy, fontSize: 16.5, fontWeight: FontWeight.w800, letterSpacing: -.2),
                      ),
                    ),
                    if (subtitle != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 1),
                        child: Text(
                          subtitle!,
                          style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                        ),
                      ),
                  ],
                ),
              ),
              if (trailing != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6, left: 8),
                  child: Text(
                    trailing!,
                    style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _ProductIdentity extends StatelessWidget {
  const _ProductIdentity({required this.product});
  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final category = productCategoryLabel(product);
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          SizedBox(
            width: 56,
            height: 56,
            child: ProductNetworkImage(url: productImageUrl(product), fit: BoxFit.cover, borderRadius: 8),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${product['name'] ?? 'Produit'}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w800, height: 1.2),
                ),
                if (category.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(
                      category,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MainAction extends StatelessWidget {
  const _MainAction({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 4),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, color: Mk.orange, size: 22),
              const SizedBox(width: 8),
              Text(
                label,
                style: const TextStyle(color: Mk.navy, fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _GalleryTile extends StatelessWidget {
  const _GalleryTile({
    required this.index,
    required this.image,
    required this.main,
    required this.saving,
    required this.onPreview,
    required this.onDelete,
    required this.onSetMain,
  });

  final int index;
  final Map<String, dynamic> image;
  final bool main;
  final bool saving;
  final VoidCallback onPreview;
  final VoidCallback onDelete;
  final VoidCallback onSetMain;

  Widget _chip({required Widget child, VoidCallback? onTap, String? tooltip}) {
    final chip = Container(
      width: 28,
      height: 28,
      alignment: Alignment.center,
      decoration: const BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: [BoxShadow(color: Color(0x260A1B4F), blurRadius: 4)],
      ),
      child: child,
    );
    if (onTap == null && tooltip == null) return Padding(padding: const EdgeInsets.all(4), child: chip);
    return Tooltip(
      message: tooltip ?? '',
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: Padding(padding: const EdgeInsets.all(4), child: chip),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: main ? Mk.orange : _line, width: main ? 1.6 : 1.1),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned.fill(
            child: InkWell(
              onTap: onPreview,
              child: ProductNetworkImage(url: image['card_url'] ?? image['url'], fit: BoxFit.cover, borderRadius: 0),
            ),
          ),
          Positioned(
            left: 0,
            top: 0,
            child: _chip(
              child: Text(
                '${index + 1}',
                style: const TextStyle(color: Mk.navy, fontSize: 12.5, fontWeight: FontWeight.w800),
              ),
            ),
          ),
          Positioned(
            right: 0,
            top: 0,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _chip(
                  tooltip: main ? 'Image principale' : 'Définir comme image principale',
                  onTap: main || saving ? null : onSetMain,
                  child: Icon(main ? Icons.star_rounded : Icons.star_border_rounded, size: 19, color: main ? Mk.orange : Mk.icon),
                ),
                _chip(
                  tooltip: 'Supprimer cette image',
                  onTap: saving ? null : onDelete,
                  child: const Icon(Icons.delete_outline_rounded, size: 18, color: Mk.icon),
                ),
              ],
            ),
          ),
          if (main)
            Positioned(
              left: 4,
              bottom: 5,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: Mk.orange, borderRadius: BorderRadius.circular(99)),
                child: const Text(
                  'Principale',
                  style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Tuile en pointillés orange pour ajouter une image.
class _AddTile extends StatelessWidget {
  const _AddTile({required this.onTap, required this.label, this.height});
  final VoidCallback? onTap;
  final String label;
  final double? height;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      child: SizedBox(
        height: height,
        child: MkDashedBox(
          orange: true,
          onTap: onTap,
          padding: const EdgeInsets.all(6),
          child: Center(
            // Petite tuile ou texte agrandi : le contenu se réduit au lieu de déborder.
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: const BoxDecoration(color: Mk.orange, shape: BoxShape.circle),
                    child: const Icon(Icons.add_rounded, color: Colors.white, size: 24),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    label,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    style: const TextStyle(color: Color(0xFFE85400), fontSize: 12, fontWeight: FontWeight.w700, height: 1.2),
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

/// Liste de conseils cochés en vert.
class _CheckList extends StatelessWidget {
  const _CheckList(this.items);
  final List<String> items;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _line, width: 1.2),
      ),
      child: Column(
        children: [
          for (var i = 0; i < items.length; i++) ...[
            if (i > 0) const Divider(height: 1, color: _line),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Row(
                children: [
                  const Icon(Icons.check_circle_rounded, color: Color(0xFF22A45D), size: 21),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      items[i],
                      style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Traitements appliqués d'office par le serveur : affichés comme toujours actifs.
class _AutoList extends StatelessWidget {
  const _AutoList(this.items);
  final List<String> items;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _line, width: 1.2),
      ),
      child: Column(
        children: [
          for (var i = 0; i < items.length; i++) ...[
            if (i > 0) const Divider(height: 1, color: _line),
            Semantics(
              label: '${items[i]} : toujours actif',
              excludeSemantics: true,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        items[i],
                        style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      width: 40,
                      height: 22,
                      padding: const EdgeInsets.all(2),
                      alignment: Alignment.centerRight,
                      decoration: BoxDecoration(color: _blue, borderRadius: BorderRadius.circular(99)),
                      child: Container(
                        width: 18,
                        height: 18,
                        decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SourceRow extends StatelessWidget {
  const _SourceRow({required this.icon, required this.color, required this.label, required this.onTap});
  final IconData icon;
  final Color color;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(color: color.withValues(alpha: .1), borderRadius: BorderRadius.circular(14)),
              child: Icon(icon, color: color, size: 25),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(color: Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w600),
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
          ],
        ),
      ),
    );
  }
}
