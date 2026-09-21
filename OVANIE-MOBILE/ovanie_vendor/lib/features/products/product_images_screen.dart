import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../data/vendor_repository.dart';
import '../shell/vendor_tabs.dart';
import 'product_ui.dart';

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
      final data = await VendorRepository.instance.product(widget.productId);
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

  Map<String, dynamic>? get _mainImage {
    for (final image in _images) {
      if (image['is_main'] == true || '${image['is_main']}' == '1') return image;
    }
    return _images.isEmpty ? null : _images.first;
  }

  Future<ImageSource?> _sourceSheet() async {
    return showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
          decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 44, height: 4, decoration: BoxDecoration(color: const Color(0xFFD5DBE5), borderRadius: BorderRadius.circular(99))),
            const SizedBox(height: 14),
            const Text('Ajouter une image', style: TextStyle(color: vendorText, fontSize: 18, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            ListTile(leading: const Icon(Icons.photo_library_outlined, color: vendorBlue), title: const Text('Importer depuis la galerie'), onTap: () => Navigator.pop(sheetContext, ImageSource.gallery)),
            ListTile(leading: const Icon(Icons.photo_camera_outlined, color: productOrange), title: const Text('Prendre une photo'), onTap: () => Navigator.pop(sheetContext, ImageSource.camera)),
          ]),
        ),
      ),
    );
  }

  Future<void> _add({bool main = false}) async {
    if (_images.length >= 10) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Maximum 10 images par produit.')));
      return;
    }
    final source = await _sourceSheet();
    if (source == null) return;
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 88, maxWidth: 1800, maxHeight: 1800);
    if (picked == null) return;
    setState(() => _saving = true);
    try {
      await VendorRepository.instance.addProductImage(widget.productId, File(picked.path), isMain: main || _images.isEmpty);
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _replace(Map<String, dynamic> image) async {
    final source = await _sourceSheet();
    if (source == null) return;
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 88, maxWidth: 1800, maxHeight: 1800);
    if (picked == null) return;
    setState(() => _saving = true);
    try {
      await VendorRepository.instance.replaceProductImage(int.parse('${image['id']}'), File(picked.path));
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _setMain(Map<String, dynamic> image) async {
    setState(() => _saving = true);
    try {
      await VendorRepository.instance.setMainProductImage(int.parse('${image['id']}'));
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _delete(Map<String, dynamic> image) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Supprimer cette image ?'),
        content: const Text('L’image sera retirée définitivement de la galerie produit.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _saving = true);
    try {
      await VendorRepository.instance.deleteProductImage(int.parse('${image['id']}'));
      await _load();
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _showCropInfo() async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(width: 44, height: 4, decoration: BoxDecoration(color: const Color(0xFFD5DBE5), borderRadius: BorderRadius.circular(99))),
              const SizedBox(height: 16),
              const Icon(Icons.crop_square_rounded, color: productOrange, size: 38),
              const SizedBox(height: 10),
              const Text('Recadrage OVANIE 1:1', style: TextStyle(color: vendorText, fontSize: 19, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              const Text(
                'Le recadrage carré, le fond clair et les variantes optimisées sont appliqués automatiquement au moment de l’import ou du remplacement de l’image.',
                textAlign: TextAlign.center,
                style: TextStyle(color: vendorMuted, height: 1.4),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () => Navigator.pop(sheetContext),
                  child: const Text('Compris'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _preview(Map<String, dynamic> image) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        insetPadding: const EdgeInsets.all(18),
        child: AspectRatio(
          aspectRatio: 1,
          child: Stack(children: [
            Positioned.fill(child: ProductNetworkImage(url: image['url'], fit: BoxFit.contain, borderRadius: 16)),
            Positioned(top: 6, right: 6, child: IconButton.filled(onPressed: () => Navigator.pop(dialogContext), icon: const Icon(Icons.close_rounded))),
          ]),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final p = _product ?? <String, dynamic>{};
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFD),
      body: Column(
        children: <Widget>[
          const ProductHeader(
            title: 'Gestion des images produit',
            subtitle: 'Ajoutez, organisez et optimisez les visuels de votre produit',
            showBack: true,
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : ListView(
                    padding: const EdgeInsets.fromLTRB(14, 0, 14, 26),
                    children: <Widget>[
                      Transform.translate(
                        offset: const Offset(0, -18),
                        child: Container(
                          padding: const EdgeInsets.fromLTRB(14, 10, 14, 16),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(22),
                            border: Border.all(color: const Color(0xFFE2E8F1)),
                            boxShadow: const <BoxShadow>[
                              BoxShadow(color: Color(0x12031D47), blurRadius: 20, offset: Offset(0, 8)),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: <Widget>[
                              if (_error != null) ...<Widget>[
                                VendorErrorBox(_error),
                                const SizedBox(height: 10),
                              ],
                              _ProductIdentity(product: p),
                              const Divider(height: 18, color: productBorder),
                              _sectionHeading('1', 'Image principale'),
                              const SizedBox(height: 8),
                              _mainImageBlock(),
                              const SizedBox(height: 12),
                              _sectionHeading('2', 'Galerie produit', subtitle: 'Touchez ⭐ pour définir l’image principale'),
                              const SizedBox(height: 8),
                              _galleryGrid(),
                              const Divider(height: 20, color: productBorder),
                              LayoutBuilder(
                                builder: (context, c) {
                                  final quality = _qualityBlock();
                                  final optimisation = _optimisationBlock();
                                  if (c.maxWidth < 350) {
                                    return Column(children: <Widget>[quality, const SizedBox(height: 14), optimisation]);
                                  }
                                  return Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: <Widget>[
                                      Expanded(child: quality),
                                      const SizedBox(width: 12),
                                      Expanded(child: optimisation),
                                    ],
                                  );
                                },
                              ),
                              const SizedBox(height: 14),
                              _sectionHeading('5', 'Actions'),
                              const SizedBox(height: 8),
                              Row(
                                children: <Widget>[
                                  Expanded(
                                    child: SizedBox(
                                      height: 50,
                                      child: OutlinedButton.icon(
                                        onPressed: _saving
                                            ? null
                                            : () => ScaffoldMessenger.of(context).showSnackBar(
                                                  const SnackBar(content: Text('Vos modifications sont déjà enregistrées.')),
                                                ),
                                        icon: const Icon(Icons.download_outlined, color: productOrange),
                                        label: const Text('Enregistrer', style: TextStyle(color: productOrange, fontWeight: FontWeight.w900)),
                                        style: OutlinedButton.styleFrom(
                                          side: const BorderSide(color: productOrange, width: 1.4),
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                        ),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: SizedBox(
                                      height: 50,
                                      child: FilledButton.icon(
                                        onPressed: _saving ? null : () => Navigator.pop(context, true),
                                        icon: _saving
                                            ? const SizedBox.square(
                                                dimension: 18,
                                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                              )
                                            : const Icon(Icons.check_circle_outline_rounded, color: Colors.white),
                                        label: const Text('Valider les images', style: TextStyle(fontWeight: FontWeight.w900)),
                                        style: FilledButton.styleFrom(
                                          backgroundColor: productOrange,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
          ),
        ],
      ),
      bottomNavigationBar: const VendorBottomBar(selected: VendorTab.products),
    );
  }

  Widget _sectionHeading(String number, String title, {String? subtitle}) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Container(
          width: 25,
          height: 25,
          alignment: Alignment.center,
          decoration: const BoxDecoration(color: productNavy, shape: BoxShape.circle),
          child: Text(number, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w900)),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(title, style: const TextStyle(color: vendorText, fontSize: 15.5, fontWeight: FontWeight.w900)),
              if (subtitle != null) ...<Widget>[
                const SizedBox(height: 1),
                Text(subtitle, style: const TextStyle(color: vendorMuted, fontSize: 11.5, height: 1.25)),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _mainImageBlock() {
    final main = _mainImage;
    if (main == null) {
      return _AddTile(onTap: _saving ? null : () => _add(main: true), label: 'Ajouter l’image principale');
    }
    return LayoutBuilder(
      builder: (context, c) {
        final image = SizedBox(
          width: 166,
          height: 148,
          child: Stack(
            children: <Widget>[
              Positioned.fill(
                child: Container(
                  padding: const EdgeInsets.all(5),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(color: const Color(0xFF9CA7B8)),
                    borderRadius: BorderRadius.circular(9),
                  ),
                  child: ProductNetworkImage(url: main['url'], fit: BoxFit.contain, borderRadius: 7),
                ),
              ),
              Positioned(
                left: 5,
                top: 5,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                  decoration: BoxDecoration(color: productDeep, borderRadius: BorderRadius.circular(6)),
                  child: const Text('Image principale', style: TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w700)),
                ),
              ),
            ],
          ),
        );
        final actions = SizedBox(
          width: 92,
          child: Column(
            children: <Widget>[
              _ImageActionButton(icon: Icons.swap_horiz_rounded, label: 'Remplacer', onTap: _saving ? null : () => _replace(main)),
              const SizedBox(height: 8),
              _ImageActionButton(icon: Icons.crop_rounded, label: 'Recadrer', onTap: _showCropInfo),
              const SizedBox(height: 8),
              _ImageActionButton(icon: Icons.visibility_outlined, label: 'Aperçu', onTap: () => _preview(main)),
            ],
          ),
        );
        if (c.maxWidth < 290) {
          return Column(children: <Widget>[image, const SizedBox(height: 10), actions]);
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            image,
            const SizedBox(width: 22),
            actions,
          ],
        );
      },
    );
  }

  Widget _galleryGrid() {
    final visible = _images.take(8).toList();
    final addSlots = (6 - visible.length).clamp(0, 2).toInt();
    final children = <Widget>[];
    for (var i = 0; i < visible.length; i++) {
      final image = visible[i];
      final main = image['is_main'] == true || '${image['is_main']}' == '1';
      children.add(
        _GalleryTile(
          index: i,
          image: image,
          main: main,
          saving: _saving,
          onPreview: () => _preview(image),
          onDelete: () => _delete(image),
          onSetMain: main ? null : () => _setMain(image),
        ),
      );
    }
    for (var i = 0; i < addSlots; i++) {
      children.add(_GalleryAddTile(onTap: _saving ? null : _add));
    }
    if (children.isEmpty) children.add(_GalleryAddTile(onTap: _saving ? null : _add));

    return LayoutBuilder(
      builder: (context, c) {
        final columns = c.maxWidth >= 390 ? 4 : 3;
        final gap = 8.0;
        final itemWidth = (c.maxWidth - gap * (columns - 1)) / columns;
        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: children.map((child) => SizedBox(width: itemWidth, height: itemWidth * .93, child: child)).toList(),
        );
      },
    );
  }

  Widget _qualityBlock() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        _sectionHeading('3', 'Qualité et format'),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(9),
          decoration: BoxDecoration(
            color: const Color(0xFFF7FAFF),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFF7EB2FF)),
          ),
          child: const Text(
            '• Format recommandé : JPG ou PNG\n'
            '• Ratio conseillé : carré 1:1\n'
            '• Taille max : 5 Mo par image\n'
            '• Ajoutez plusieurs angles pour rassurer les acheteurs\n'
            '• Évitez les images floues ou avec texte',
            style: TextStyle(color: vendorText, fontSize: 10.5, height: 1.45),
          ),
        ),
      ],
    );
  }

  Widget _optimisationBlock() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        _sectionHeading('4', 'Optimisation automatique'),
        const SizedBox(height: 8),
        const _OptimisationSwitch(icon: Icons.crop_square_rounded, label: 'Normaliser au format carré 1:1'),
        const _OptimisationSwitch(icon: Icons.light_mode_outlined, label: 'Fond clair automatique'),
        const _OptimisationSwitch(icon: Icons.open_in_full_rounded, label: 'Compression optimisée web'),
      ],
    );
  }
}

class _ProductIdentity extends StatelessWidget {
  const _ProductIdentity({required this.product});
  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final category = product['category'] is Map ? '${(product['category'] as Map)['name'] ?? ''}' : '';
    final subcategory = product['subcategory'] is Map ? '${(product['subcategory'] as Map)['name'] ?? ''}' : '';
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: <Widget>[
          SizedBox(width: 58, height: 58, child: ProductNetworkImage(url: product['image_url'], fit: BoxFit.contain, borderRadius: 8)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('${product['name'] ?? 'Produit'}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: vendorText, fontSize: 17, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(
                  <String>[category, subcategory].where((e) => e.trim().isNotEmpty).join(' • '),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: vendorMuted, fontSize: 12.5),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          ProductStatusBadge(productStatus(product)),
        ],
      ),
    );
  }
}

class _ImageActionButton extends StatelessWidget {
  const _ImageActionButton({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 38,
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: onTap,
        icon: Icon(icon, size: 17, color: vendorText),
        label: Text(label, style: const TextStyle(color: vendorText, fontSize: 11, fontWeight: FontWeight.w800)),
        style: OutlinedButton.styleFrom(
          padding: const EdgeInsets.symmetric(horizontal: 7),
          side: const BorderSide(color: productBorder),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
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
  final VoidCallback? onSetMain;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPreview,
      borderRadius: BorderRadius.circular(9),
      child: Container(
        padding: const EdgeInsets.all(3),
        decoration: BoxDecoration(color: Colors.white, border: Border.all(color: productBorder), borderRadius: BorderRadius.circular(9)),
        child: Stack(
          children: <Widget>[
            Positioned.fill(child: ProductNetworkImage(url: image['url'], fit: BoxFit.cover, borderRadius: 7)),
            Positioned(
              left: 3,
              top: 3,
              child: Container(
                width: 22,
                height: 22,
                alignment: Alignment.center,
                decoration: const BoxDecoration(color: productNavy, shape: BoxShape.circle),
                child: Text('${index + 1}', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900)),
              ),
            ),
            if (onSetMain != null)
              Positioned(
                left: 27,
                top: 3,
                child: InkWell(
                  onTap: saving ? null : onSetMain,
                  child: Container(
                    width: 22,
                    height: 22,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: .95), borderRadius: BorderRadius.circular(6)),
                    child: const Icon(Icons.star_border_rounded, size: 16, color: productOrange),
                  ),
                ),
              ),
            Positioned(
              right: 3,
              top: 3,
              child: InkWell(
                onTap: saving ? null : onDelete,
                child: Container(
                  width: 24,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: .96), borderRadius: BorderRadius.circular(6)),
                  child: const Icon(Icons.delete_outline_rounded, size: 16, color: Colors.red),
                ),
              ),
            ),
            if (main)
              Positioned(
                left: 3,
                bottom: 3,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 3),
                  decoration: BoxDecoration(color: productDeep, borderRadius: BorderRadius.circular(5)),
                  child: const Text('Principale', style: TextStyle(color: Colors.white, fontSize: 8.5, fontWeight: FontWeight.w700)),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _GalleryAddTile extends StatelessWidget {
  const _GalleryAddTile({required this.onTap});
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(9),
      child: Container(
        decoration: BoxDecoration(
          color: const Color(0xFFFCFDFE),
          borderRadius: BorderRadius.circular(9),
          border: Border.all(color: const Color(0xFFC9D0DA)),
        ),
        child: Center(
          child: Container(
            width: 38,
            height: 38,
            alignment: Alignment.center,
            decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: productOrange, width: 1.3)),
            child: const Icon(Icons.add_rounded, color: productOrange, size: 28),
          ),
        ),
      ),
    );
  }
}

class _AddTile extends StatelessWidget {
  const _AddTile({required this.onTap, required this.label});
  final VoidCallback? onTap;
  final String label;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(9),
      child: Container(
        height: 142,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: const Color(0xFFFCFDFE), border: Border.all(color: const Color(0xFFC8D0DC)), borderRadius: BorderRadius.circular(9)),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            const Icon(Icons.add_photo_alternate_outlined, color: productOrange, size: 38),
            const SizedBox(height: 7),
            Text(label, style: const TextStyle(color: vendorText, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class _OptimisationSwitch extends StatelessWidget {
  const _OptimisationSwitch({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 38,
      padding: const EdgeInsets.symmetric(horizontal: 8),
      decoration: BoxDecoration(color: Colors.white, border: Border.all(color: productBorder), borderRadius: BorderRadius.circular(7)),
      child: Row(
        children: <Widget>[
          Icon(icon, color: vendorText, size: 18),
          const SizedBox(width: 6),
          Expanded(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: vendorText, fontSize: 10.5, fontWeight: FontWeight.w700))),
          Container(
            width: 34,
            height: 20,
            padding: const EdgeInsets.all(2),
            decoration: BoxDecoration(color: productOrange, borderRadius: BorderRadius.circular(99)),
            child: const Align(alignment: Alignment.centerRight, child: CircleAvatar(radius: 8, backgroundColor: Colors.white)),
          ),
        ],
      ),
    );
  }
}
