import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../core/ui/vendor_select.dart';
import '../../data/vendor_repository.dart';

class ProductFormScreen extends StatefulWidget {
  const ProductFormScreen({super.key, this.productId});

  final int? productId;

  @override
  State<ProductFormScreen> createState() => _ProductFormScreenState();
}

class _ProductFormScreenState extends State<ProductFormScreen> {
  final _repo = VendorRepository.instance;
  final _forms = List.generate(5, (_) => GlobalKey<FormState>());
  final Map<String, TextEditingController> _controllers = {};

  Map<String, dynamic> _meta = const {};
  Map<String, dynamic> _existing = const {};
  bool _loading = true;
  bool _saving = false;
  bool _fragile = false;
  bool _unloading = false;
  bool _negotiable = false;
  int _step = 0;
  int? _mainCategoryId;
  int? _subcategoryId;
  String? _unit;
  String? _productType;
  String _productState = 'new';
  String? _saleType;
  String? _usage;
  String? _warranty;
  List<File> _images = <File>[];
  File? _technicalSheet;
  File? _video;
  String? _error;
  bool _hasVariants = false;
  final List<_VariantDraft> _variants = <_VariantDraft>[];

  Map<String, String> _optionMap(String key) {
    final raw = _meta[key];
    if (raw is! List) return const <String, String>{};
    final result = <String, String>{};
    for (final item in raw.whereType<Map>()) {
      final code = '${item['code'] ?? item['value'] ?? ''}'.trim();
      final label = '${item['label'] ?? code}'.trim();
      if (code.isNotEmpty && label.isNotEmpty) result[code] = label;
    }
    return result;
  }

  Map<String, String> get _productTypes => _optionMap('product_types');

  Map<String, String> get _productStates => _optionMap('product_states');

  Map<String, String> get _saleTypes {
    final remote = _optionMap('selling_modes');
    final result = <String, String>{};
    for (final entry in remote.entries) {
      result[_legacySellingModeCode(entry.key)] = entry.value;
    }
    return result;
  }

  String _legacySellingModeCode(String canonical) => switch (canonical) {
        'unit' => 'standard',
        'linear_meter' => 'meter',
        _ => canonical,
      };

  String _canonicalSellingModeCode(String legacy) => switch (legacy) {
        'standard' => 'unit',
        'meter' => 'linear_meter',
        _ => legacy,
      };

  static const _usages = <String, String>{
    'construction': 'Construction',
    'renovation': 'Rénovation',
    'interieur': 'Intérieur',
    'exterieur': 'Extérieur',
    'chantier': 'Chantier',
    'professionnel': 'Usage professionnel',
  };

  static const _warranties = <String, String>{
    'aucune': 'Aucune garantie',
    '3_mois': '3 mois',
    '6_mois': '6 mois',
    '1_an': '1 an',
    '2_ans': '2 ans',
    '5_ans': '5 ans',
  };

  TextEditingController ctrl(String key, [Object? initial]) {
    return _controllers.putIfAbsent(
      key,
      () => TextEditingController(text: '${initial ?? ''}'),
    );
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    for (final variant in _variants) {
      variant.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final results = await Future.wait<Map<String, dynamic>>([
        _repo.productMeta(),
        if (widget.productId != null)
          _repo.product(widget.productId!)
        else
          Future<Map<String, dynamic>>.value(const <String, dynamic>{}),
      ]);
      _meta = results[0];
      if (widget.productId != null) {
        final data = results[1];
        _existing = data['product'] is Map
            ? Map<String, dynamic>.from(data['product'] as Map)
            : const <String, dynamic>{};
      }

      for (final key in <String>[
        'name',
        'brand',
        'short_description',
        'description',
        'price',
        'promo_price',
        'stock',
        'unit_label',
        'min_order_quantity',
        'price_p1',
        'price_p2',
        'price_p3',
        'technical_details',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'unloading_instructions',
      ]) {
        ctrl(key, _existing[key]);
      }
      // Libellé d'unité tel que saisi : `unit_label` ne renvoie que l'unité normalisée.
      if (_existing.containsKey('seller_unit_label')) {
        ctrl('unit_label').text = '${_existing['seller_unit_label'] ?? ''}'.trim();
      }
      ctrl('keywords', _existingKeyword());

      final category = _existing['category'];
      final categoryId = category is Map
          ? int.tryParse('${category['id']}')
          : null;
      _resolveCategory(categoryId);

      final existingUnit = '${_existing['unit'] ?? ''}'.trim();
      if (existingUnit.isNotEmpty && existingUnit != 'null')
        _unit = existingUnit;

      final existingType = '${_existing['type'] ?? ''}'.trim();
      if (existingType.isNotEmpty && existingType != 'null') {
        _productType = _productTypes.containsKey(existingType)
            ? existingType
            : 'autre';
      }

      final existingState = '${_existing['product_state'] ?? ''}'.trim();
      if (_productStates.containsKey(existingState)) {
        _productState = existingState;
      }

      final canonicalMode = '${_existing['selling_mode'] ?? ''}'.trim();
      final saleType = canonicalMode.isNotEmpty && canonicalMode != 'null'
          ? _legacySellingModeCode(canonicalMode)
          : '${_existing['sale_type'] ?? ''}'.trim();
      if (_saleTypes.containsKey(saleType)) _saleType = saleType;

      final usage = '${_existing['usage_area'] ?? ''}'.trim();
      if (usage.isNotEmpty && usage != 'null') {
        for (final entry in _usages.entries) {
          if (entry.key == usage ||
              entry.value.toLowerCase() == usage.toLowerCase()) {
            _usage = entry.key;
            break;
          }
        }
      }

      final warranty = '${_existing['warranty'] ?? ''}'.trim();
      if (warranty.isNotEmpty && warranty != 'null') {
        for (final entry in _warranties.entries) {
          if (entry.key == warranty ||
              entry.value.toLowerCase() == warranty.toLowerCase()) {
            _warranty = entry.key;
            break;
          }
        }
      }

      _fragile =
          _existing['fragile'] == true || '${_existing['fragile']}' == '1';
      _unloading =
          _existing['requires_unloading'] == true ||
          '${_existing['requires_unloading']}' == '1';
      _negotiable =
          _existing['is_negotiable'] == true ||
          '${_existing['is_negotiable']}' == '1';
      if (widget.productId == null) {
        // Valeurs les plus courantes pré-remplies : le vendeur ne les change que si besoin.
        if (_unit == null && _units.contains('piece')) _unit = 'piece';
        if (_saleType == null && _saleTypes.isNotEmpty) {
          _saleType = _saleTypes.containsKey('standard')
              ? 'standard'
              : _saleTypes.keys.first;
        }
        if (ctrl('min_order_quantity').text.trim().isEmpty) {
          ctrl('min_order_quantity').text = '1';
        }
      }
      final rawVariants = _existing['variants'];
      if (rawVariants is List && rawVariants.isNotEmpty) {
        _hasVariants = true;
        for (final raw in rawVariants.whereType<Map>()) {
          _variants.add(_VariantDraft.fromMap(Map<String, dynamic>.from(raw)));
        }
      }
    } catch (e) {
      _error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _existingKeyword() {
    final raw = _existing['product_attributes'];
    if (raw is List) {
      for (final item in raw.whereType<Map>()) {
        if ('${item['label'] ?? ''}'.trim().toLowerCase() == 'mots-clés') {
          return '${item['value'] ?? ''}';
        }
      }
    }
    return '';
  }

  List<Map<String, dynamic>> get _categoryTree =>
      ((_meta['product_categories'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();

  List<Map<String, dynamic>> _childrenFor(int? mainId) {
    if (mainId == null) return const [];
    for (final root in _categoryTree) {
      if (int.tryParse('${root['id']}') == mainId) {
        return ((root['children'] as List?) ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
      }
    }
    return const [];
  }

  void _resolveCategory(int? selectedId) {
    if (selectedId == null) return;
    for (final root in _categoryTree) {
      final rootId = int.tryParse('${root['id']}');
      if (rootId == selectedId) {
        _mainCategoryId = rootId;
        return;
      }
      for (final child
          in ((root['children'] as List?) ?? const []).whereType<Map>()) {
        final childId = int.tryParse('${child['id']}');
        if (childId == selectedId) {
          _mainCategoryId = rootId;
          _subcategoryId = childId;
          return;
        }
      }
    }
  }

  List<String> get _units {
    final values =
        ((_meta['product_units'] as List?) ?? const <String>['piece'])
            .map((value) => '$value')
            .where((value) => value.trim().isNotEmpty)
            .toList();
    return values.isEmpty ? const ['piece'] : values;
  }

  String _unitLabel(String unit) {
    final remote = _optionMap('product_unit_options');
    if (remote.containsKey(unit)) return remote[unit]!;
    const labels = <String, String>{
      'piece': 'Pièce',
      'sac': 'Sac',
      'carton': 'Carton',
      'kg': 'Kg',
      'tonne': 'Tonne',
      'litre': 'Litre',
      'm2': 'm²',
      'm3': 'm³',
      'ml': 'Mètre linéaire',
      'palette': 'Palette',
      'rouleau': 'Rouleau',
      'seau': 'Seau',
      'paquet': 'Paquet',
      'barre': 'Barre',
      'bidon': 'Bidon',
    };
    return labels[unit] ?? unit;
  }

  bool _pickingImages = false;
  Future<void> _addImages(ImageSource source) async {
    if (_pickingImages || _images.length >= 10) return;
    setState(() => _pickingImages = true);
    try {
      final picker = ImagePicker();
      final List<XFile> picked;
      if (source == ImageSource.camera) {
        final photo = await picker.pickImage(
          source: source,
          imageQuality: 90,
          maxWidth: 2000,
          maxHeight: 2000,
        );
        picked = photo == null ? [] : [photo];
      } else {
        picked = await picker.pickMultiImage(
          imageQuality: 90,
          maxWidth: 2000,
          maxHeight: 2000,
        );
      }
      if (!mounted) return;
      setState(() {
        _images.addAll(
          picked.take(10 - _images.length).map((item) => File(item.path)),
        );
        if (_images.isNotEmpty) _error = null;
      });
    } catch (_) {
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Impossible d’ajouter la photo. Vérifiez l’autorisation de l’appareil photo ou choisissez une image dans la galerie.',
            ),
          ),
        );
    } finally {
      if (mounted) setState(() => _pickingImages = false);
    }
  }

  Future<void> _pickTechnicalSheet() async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
    );
    final path = result?.files.single.path;
    if (path != null && mounted) setState(() => _technicalSheet = File(path));
  }

  Future<void> _pickVideo() async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['mp4', 'mov', 'webm', 'm4v'],
    );
    final path = result?.files.single.path;
    if (path != null && mounted) setState(() => _video = File(path));
  }

  double get _volumeM3 {
    final length =
        double.tryParse(ctrl('length_cm').text.replaceAll(',', '.')) ?? 0;
    final width =
        double.tryParse(ctrl('width_cm').text.replaceAll(',', '.')) ?? 0;
    final height =
        double.tryParse(ctrl('height_cm').text.replaceAll(',', '.')) ?? 0;
    if (length <= 0 || width <= 0 || height <= 0) return 0;
    return (length * width * height) / 1000000;
  }

  bool _validateCurrentStep({bool draft = false}) {
    FocusManager.instance.primaryFocus?.unfocus();
    final form = _forms[_step].currentState;
    if (form != null && !form.validate()) return false;

    if (_step == 0) {
      if (_mainCategoryId == null)
        return _showValidation(
          'Sélectionnez la catégorie principale du produit.',
        );
      if (_childrenFor(_mainCategoryId).isNotEmpty && _subcategoryId == null) {
        return _showValidation('Sélectionnez la sous-catégorie du produit.');
      }
    }
    if (_step == 1) {
      if (_unit == null)
        return _showValidation('Sélectionnez l’unité de vente.');
      if (_saleType == null)
        return _showValidation('Sélectionnez le mode de vente.');
      if (_negotiable) {
        double read(String key) => double.tryParse(ctrl(key).text.trim().replaceAll(' ', '').replaceAll(',', '.')) ?? 0;
        final price = read('price');
        final p1 = read('price_p1'), p2 = read('price_p2'), p3 = read('price_p3');
        if (!(p1 > 0 && p2 > 0 && p3 > 0 && p1 < price && p2 <= p1 && p3 <= p2)) {
          return _showValidation('Les offres doivent être inférieures au prix et décroissantes.');
        }
      }
    }
    if (_step == 3 &&
        !draft &&
        _unloading &&
        ctrl('unloading_instructions').text.trim().isEmpty) {
      return _showValidation('Précisez les besoins de déchargement.');
    }
    if (_step == 4 && !draft && widget.productId == null && _images.isEmpty) {
      return _showValidation(
        'Ajoutez au moins une image du produit avant publication.',
      );
    }
    setState(() => _error = null);
    return true;
  }

  bool _showValidation(String message) {
    setState(() => _error = message);
    return false;
  }

  void _next() {
    if (_saving || !_validateCurrentStep()) return;
    if (_step < 4)
      setState(() {
        _step++;
        _error = null;
      });
  }

  void _prefillNegotiation() {
    final price = double.tryParse(ctrl('price').text.trim().replaceAll(' ', '').replaceAll(',', '.'));
    if (price == null || price <= 0) return;
    for (final entry in {'price_p1': .95, 'price_p2': .90, 'price_p3': .85}.entries) {
      if (ctrl(entry.key).text.trim().isEmpty) {
        ctrl(entry.key).text = (price * entry.value).round().toString();
      }
    }
  }

  Future<void> _previous() async {
    FocusManager.instance.primaryFocus?.unfocus();
    if (_saving) return;
    if (_step == 0) {
      final dirty = _controllers.values.any((c) => c.text.trim().isNotEmpty) ||
          _images.isNotEmpty;
      if (dirty && widget.productId == null) {
        final leave = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Quitter sans enregistrer ?'),
            content: const Text('Les informations saisies seront perdues.'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Continuer'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Quitter'),
              ),
            ],
          ),
        );
        if (leave != true || !mounted) return;
      }
      if (mounted) Navigator.of(context).pop();
      return;
    }
    setState(() {
      _step--;
      _error = null;
    });
  }

  Future<void> _save({required bool draft}) async {
    if (!_validateCurrentStep(draft: draft)) return;
    if (!draft) {
      if (ctrl('name').text.trim().isEmpty ||
          _mainCategoryId == null ||
          ctrl('description').text.trim().isEmpty ||
          ctrl('price').text.trim().isEmpty ||
          ctrl('stock').text.trim().isEmpty ||
          _unit == null ||
          ctrl('min_order_quantity').text.trim().isEmpty ||
          _saleType == null ||
          ctrl('weight_kg').text.trim().isEmpty ||
          ctrl('length_cm').text.trim().isEmpty ||
          ctrl('width_cm').text.trim().isEmpty ||
          ctrl('height_cm').text.trim().isEmpty) {
        _showValidation(
          'Complétez les informations obligatoires des étapes précédentes avant publication.',
        );
        return;
      }
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final categoryId = _subcategoryId ?? _mainCategoryId;
      final values = <String, dynamic>{
        'name': ctrl('name').text.trim(),
        'category_id': categoryId,
        'brand': ctrl('brand').text.trim(),
        'type': _productType ?? 'materiau',
        'product_state': _productState,
        'short_description': ctrl('short_description').text.trim(),
        'description': ctrl('description').text.trim(),
        'price': ctrl('price').text.trim(),
        'promo_price': ctrl('promo_price').text.trim(),
        'stock': ctrl('stock').text.trim(),
        'unit': _unit ?? 'piece',
        'unit_label': ctrl('unit_label').text.trim(),
        'min_order_quantity': ctrl('min_order_quantity').text.trim().isEmpty
            ? '1'
            : ctrl('min_order_quantity').text.trim(),
        'selling_mode': _canonicalSellingModeCode(_saleType ?? 'standard'),
        'sale_type': _saleType ?? 'standard',
        'usage_area': _usages[_usage] ?? '',
        'technical_details': ctrl('technical_details').text.trim(),
        'warranty': _warranties[_warranty] ?? '',
        'weight_kg': ctrl('weight_kg').text.trim(),
        'length_cm': ctrl('length_cm').text.trim(),
        'width_cm': ctrl('width_cm').text.trim(),
        'height_cm': ctrl('height_cm').text.trim(),
        'volume_m3': _volumeM3.toStringAsFixed(4),
        'fragile': _fragile ? 1 : 0,
        'requires_unloading': _unloading ? 1 : 0,
        'unloading_instructions': ctrl('unloading_instructions').text.trim(),
        'is_negotiable': _negotiable ? 1 : 0,
        'availability_status':
            (int.tryParse(ctrl('stock').text.trim()) ?? 0) > 0
            ? 'in_stock'
            : 'out_of_stock',
        'save_as_draft': draft ? 1 : 0,
      };

      values['has_variants'] = _hasVariants ? 1 : 0;
      final variantImages = <int, File>{};
      if (_hasVariants) {
        for (var i = 0; i < _variants.length; i++) {
          final variant = _variants[i];
          values['variants[$i][id]'] = variant.id ?? '';
          values['variants[$i][type]'] = variant.type.text.trim().isEmpty ? 'Couleur' : variant.type.text.trim();
          values['variants[$i][value]'] = variant.value.text.trim();
          values['variants[$i][color_hex]'] = variant.colorHex.text.trim();
          values['variants[$i][stock]'] = variant.stock.text.trim().isEmpty ? '0' : variant.stock.text.trim();
          values['variants[$i][price_override]'] = variant.price.text.trim();
          values['variants[$i][is_active]'] = 1;
          if (variant.image != null) variantImages[i] = variant.image!;
        }
      }

      var attributeIndex = 0;
      final keywords = ctrl('keywords').text.trim();
      if (keywords.isNotEmpty) {
        values['product_attributes[$attributeIndex][label]'] = 'Mots-clés';
        values['product_attributes[$attributeIndex][value]'] = keywords;
        values['product_attributes[$attributeIndex][unit]'] = '';
        attributeIndex++;
      }
      if (_usage != null) {
        values['product_attributes[$attributeIndex][label]'] =
            'Usage recommandé';
        values['product_attributes[$attributeIndex][value]'] =
            _usages[_usage] ?? _usage!;
        values['product_attributes[$attributeIndex][unit]'] = '';
      }

      if (_negotiable) {
        for (final key in ['price_p1', 'price_p2', 'price_p3']) {
          final value = double.tryParse(ctrl(key).text.trim().replaceAll(' ', '').replaceAll(',', '.'));
          if (value != null && value > 0) values[key] = value.round();
        }
      }

      await _repo.saveProduct(
        values,
        productId: widget.productId,
        images: _images,
        technicalSheet: _technicalSheet,
        video: _video,
        variantImages: variantImages,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            draft
                ? 'Produit enregistré en brouillon.'
                : 'Produit publié avec succès.',
          ),
        ),
      );
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  static const _stepTitles = <String>['Informations', 'Prix & unité', 'Détail technique', 'Logistique', 'Médias'];

  /// Bandeau illustré de chaque étape : titre et phrase d'introduction.
  static const _banners = <(String, String)>[
    ('Informations du produit', 'Renseignez les informations générales de votre produit pour commencer.'),
    ('Prix & unité', 'Définissez le prix, l’unité de vente et la quantité de votre produit.'),
    ('Détail technique', 'Ajoutez les caractéristiques techniques de votre produit pour mieux informer vos clients.'),
    ('Logistique', 'Renseignez les informations logistiques de votre produit pour le calcul de la livraison.'),
    ('Médias du produit', 'Ajoutez des photos et une vidéo pour rendre votre produit plus attractif.'),
  ];

  /// Fermeture par la croix : demande confirmation si une saisie serait perdue.
  Future<void> _close() async {
    FocusManager.instance.primaryFocus?.unfocus();
    if (_saving) return;
    final dirty = _controllers.values.any((c) => c.text.trim().isNotEmpty) || _images.isNotEmpty;
    if (dirty && widget.productId == null) {
      final leave = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Quitter sans enregistrer ?'),
          content: const Text('Les informations saisies seront perdues.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Continuer')),
            TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Quitter')),
          ],
        ),
      );
      if (leave != true || !mounted) return;
    }
    if (mounted) Navigator.of(context).pop();
  }

  /// Deux champs côte à côte, empilés sur un écran étroit ou avec un texte agrandi.
  bool _sideBySide(BuildContext context) =>
      MediaQuery.sizeOf(context).width >= 350 && MediaQuery.textScalerOf(context).scale(10) <= 10.5;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _previous();
      },
      child: MkTheme(
        lightStatusBar: false,
        child: Scaffold(
          backgroundColor: const Color(0xFFF3F6FB),
          body: Column(
            children: [
              _WizardHeader(
                title: widget.productId == null ? 'Ajouter un produit' : 'Modifier le produit',
                onClose: _close,
              ),
              Expanded(
                child: _loading
                    ? const _FormLoading()
                    : ListView(
                        padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
                        children: [
                          _StepProgress(
                            step: _step,
                            titles: _stepTitles,
                            onSelect: (index) {
                              // Seules les étapes déjà validées sont accessibles directement.
                              if (index < _step && !_saving) {
                                setState(() {
                                  _step = index;
                                  _error = null;
                                });
                              }
                            },
                          ),
                          const SizedBox(height: 12),
                          _StepBanner(step: _step, title: _banners[_step].$1, subtitle: _banners[_step].$2),
                          const SizedBox(height: 10),
                          Form(key: _forms[_step], child: _currentStep()),
                          if (_error != null) ...[
                            const SizedBox(height: 10),
                            MkNotice(_error!),
                          ],
                          const SizedBox(height: 12),
                          const Padding(
                            padding: EdgeInsets.symmetric(horizontal: 4),
                            child: Row(
                              children: [
                                Icon(Icons.verified_user_outlined, color: Mk.icon, size: 20),
                                SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    'Les informations fournies seront visibles sur votre fiche produit.',
                                    style: TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
              ),
            ],
          ),
          bottomNavigationBar: _loading ? null : _bottomActions(),
        ),
      ),
    );
  }

  Widget _currentStep() {
    return switch (_step) {
      0 => _informationStep(),
      1 => _priceStep(),
      2 => _technicalStep(),
      3 => _logisticsStep(),
      _ => _mediaStep(),
    };
  }

  static const _gap = SizedBox(height: 14);

  Widget _informationStep() {
    final roots = _categoryTree;
    final children = _childrenFor(_mainCategoryId);
    final brand = _labeled('Marque', _textField('brand', 'Ex. CIMAF, Sika, Hilti…'), optional: true);
    final type = _labeled(
      'Type de produit',
      _selector<String>(
        title: 'Type de produit',
        value: _productType,
        items: _productTypes,
        hint: 'Sélectionnez un type',
        onChanged: (v) => setState(() => _productType = v),
      ),
      optional: true,
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _FormCard(
          children: [
            _labeled('Nom du produit', _textField('name', 'Ex. Ciment CPA 42.5R', required: true), required: true),
            _gap,
            _categorySelector(
              label: 'Catégorie principale',
              value: _mainCategoryId,
              items: roots,
              hint: 'Sélectionnez une catégorie',
              onChanged: (id) => setState(() {
                _mainCategoryId = id;
                _subcategoryId = null;
              }),
            ),
            _gap,
            _categorySelector(
              label: 'Sous-catégorie',
              value: _subcategoryId,
              items: children,
              hint: 'Sélectionnez une sous-catégorie',
              enabled: _mainCategoryId != null && children.isNotEmpty,
              onChanged: (id) => setState(() => _subcategoryId = id),
            ),
          ],
        ),
        const SizedBox(height: 10),
        _FormCard(
          children: [
            if (_sideBySide(context)) _twoColumns(brand, type) else ...[brand, _gap, type],
            _gap,
            _labeled(
              'État du produit',
              _selector<String>(
                title: 'État du produit',
                value: _productState,
                items: _productStates,
                hint: 'Sélectionnez l’état du produit',
                onChanged: (v) => setState(() => _productState = v),
              ),
              optional: true,
            ),
          ],
        ),
        const SizedBox(height: 10),
        _FormCard(
          children: [
            _labeled(
              'Description courte',
              _textField('short_description', 'Une brève description de votre produit', maxLength: 200),
              optional: true,
              counter: _counter('short_description', 200),
            ),
            _gap,
            _labeled(
              'Mots-clés',
              _textField('keywords', 'Ex. ciment, construction, BTP, qualité…'),
              optional: true,
              help: 'Séparez les mots-clés par une virgule ( , )',
            ),
            _gap,
            _labeled(
              'Description détaillée',
              _textField(
                'description',
                'Décrivez votre produit en détail : caractéristiques, avantages, conseils d’utilisation…',
                required: true,
                requiredMessage: 'La description est obligatoire',
                maxLength: 1000,
                minLines: 3,
                maxLines: 8,
              ),
              required: true,
              counter: _counter('description', 1000),
            ),
          ],
        ),
      ],
    );
  }

  Widget _priceStep() {
    final price = _labeled(
      'Prix normal',
      _unitField('price', 'Ex. : 6 500', 'FCFA', required: true),
      required: true,
      help: 'Prix de vente habituel de votre produit.',
    );
    final promo = _labeled(
      'Prix promo',
      _unitField('promo_price', 'Ex. : 5 900', 'FCFA'),
      optional: true,
      help: 'Prix réduit pendant une période limitée.',
    );
    final offer1 = _labeled('1re offre', _unitField('price_p1', '', 'FCFA', required: true), required: true);
    final offer2 = _labeled('2e offre', _unitField('price_p2', '', 'FCFA', required: true), required: true);

    return _FormCard(
      children: [
        if (_sideBySide(context)) _twoColumns(price, promo) else ...[price, _gap, promo],
        _gap,
        _labeled(
          'Stock disponible',
          _textField('stock', 'Ex. : 100', required: true, numeric: true),
          required: true,
          help: 'Nombre d’unités disponibles en stock.',
        ),
        _gap,
        _labeled(
          'Unité de vente',
          _selector<String>(
            title: 'Unité de vente',
            value: _unit,
            items: {for (final unit in _units) unit: _unitLabel(unit)},
            hint: 'Sélectionnez une unité',
            onChanged: (v) => setState(() => _unit = v),
          ),
          required: true,
          help: 'Ex. : Pièce, Sac, Carton, Kg, Litre, m², etc.',
        ),
        _gap,
        _labeled(
          'Libellé personnel',
          _textField('unit_label', 'Ex. : Sac (50 kg)'),
          optional: true,
          help: 'Précision ajoutée à l’unité, entre parenthèses : « Sac (50 kg) ».',
        ),
        _gap,
        _labeled(
          'Quantité minimum',
          _textField('min_order_quantity', 'Ex. : 1', required: true, numeric: true),
          required: true,
          help: 'Quantité minimale que le client peut commander.',
        ),
        _gap,
        _labeled(
          'Mode de vente',
          _selector<String>(
            title: 'Mode de vente',
            value: _saleType,
            items: _saleTypes,
            hint: 'Sélectionnez un mode de vente',
            onChanged: (v) => setState(() => _saleType = v),
          ),
          required: true,
          help: 'Ex. : Vente à l’unité, par lot, au poids, au mètre, etc.',
        ),
        _gap,
        _toggleRow(
          'Prix négociable',
          _negotiable,
          (v) => setState(() {
            _negotiable = v;
            if (v) _prefillNegotiation();
          }),
          subtitle: 'Le client pourra vous proposer un prix depuis la fiche produit.',
        ),
        if (_negotiable) ...[
          _gap,
          const Text(
            'Vos contre-offres, de la plus proche du prix à la plus basse :',
            style: TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 10),
          if (_sideBySide(context)) _twoColumns(offer1, offer2) else ...[offer1, _gap, offer2],
          _gap,
          _labeled('Prix minimum accepté', _unitField('price_p3', '', 'FCFA', required: true), required: true),
        ],
      ],
    );
  }

  Widget _technicalStep() {
    final sheetName = _technicalSheet?.path.split(Platform.pathSeparator).last;
    return _FormCard(
      children: [
        _cardHeading(Icons.settings_rounded, _banners[2].$1, _banners[2].$2),
        _gap,
        _labeled(
          'Usage recommandé',
          _selector<String>(
            title: 'Usage recommandé',
            value: _usage,
            items: _usages,
            hint: 'Sélectionnez l’usage recommandé',
            onChanged: (v) => setState(() => _usage = v),
          ),
          optional: true,
          help: 'À quoi sert ce produit ? Laissez vide si vous ne savez pas.',
        ),
        _gap,
        _labeled(
          'Détails technique',
          _textField(
            'technical_details',
            'Ex. Résiste à l’humidité, couleur grise, sac de 50 kg…',
            maxLength: 1000,
            minLines: 3,
            maxLines: 6,
          ),
          optional: true,
        ),
        _gap,
        _labeled(
          'Garantie',
          _selector<String>(
            title: 'Garantie',
            value: _warranty,
            items: _warranties,
            hint: 'Sélectionnez la durée de garantie',
            onChanged: (v) => setState(() => _warranty = v),
          ),
          optional: true,
          help: 'Ex. : 6 mois, 1 an, 2 ans, etc.',
        ),
        _gap,
        _labeled(
          'Fiche technique PDF',
          _dropZone(
            icon: Icons.picture_as_pdf_rounded,
            title: sheetName ?? 'Ajouter la fiche technique',
            line1: sheetName == null ? 'Appuyez pour choisir votre fichier' : 'Appuyez pour remplacer le fichier',
            line2: 'Format : PDF uniquement • Taille maximale : 5 Mo',
            done: sheetName != null,
            onTap: _pickTechnicalSheet,
          ),
          optional: true,
        ),
      ],
    );
  }

  Widget _logisticsStep() {
    Widget dim(String key, String label, String hint) => _labeled(
          label,
          _unitField(key, hint, 'cm', required: true, onChanged: (_) => setState(() {})),
          required: true,
        );
    final length = dim('length_cm', 'Longueur', 'Ex. : 30');
    final width = dim('width_cm', 'Largeur', 'Ex. : 20');
    final height = dim('height_cm', 'Hauteur', 'Ex. : 15');

    return _FormCard(
      children: [
        _cardHeading(Icons.local_shipping_rounded, _banners[3].$1, _banners[3].$2),
        _gap,
        _labeled('Poids', _unitField('weight_kg', 'Ex. : 2.5', 'kg', required: true), required: true),
        _gap,
        if (_sideBySide(context))
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: length),
              const SizedBox(width: 8),
              Expanded(child: width),
              const SizedBox(width: 8),
              Expanded(child: height),
            ],
          )
        else ...[length, _gap, width, _gap, height],
        _gap,
        _labeled(
          'Volume',
          Container(
            constraints: const BoxConstraints(minHeight: 48),
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: const Color(0xFFE9EDF4),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _fieldBorder),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _volumeM3 > 0 ? _volumeM3.toStringAsFixed(4).replaceAll('.', ',') : '',
                    style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w600),
                  ),
                ),
                const Text('m³', style: TextStyle(color: Mk.muted, fontSize: 14, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
          note: '(calculé auto)',
          help: 'Le volume est calculé automatiquement à partir des dimensions.',
        ),
        _gap,
        _toggleRow('Produit fragile', _fragile, (v) => setState(() => _fragile = v)),
        const SizedBox(height: 10),
        _toggleRow('Déchargement requis', _unloading, (v) => setState(() => _unloading = v)),
        if (_unloading) ...[
          _gap,
          _labeled(
            'Détails du déchargement',
            _textField(
              'unloading_instructions',
              'Ex. : chariot ou aide au déchargement nécessaire',
              required: true,
              maxLength: 255,
              minLines: 2,
              maxLines: 4,
            ),
            required: true,
          ),
        ],
        _gap,
        const MkInfoBox(
          icon: Icons.info_rounded,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Ces informations nous permettent de :',
                style: TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w800),
              ),
              SizedBox(height: 3),
              Text('Calculer les frais de livraison et de sélectionner le mode logistique le plus adapté à votre produit.'),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _pickVariantImage(int index) async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 92,
      maxWidth: 1800,
    );
    if (picked == null || !mounted) return;
    setState(() => _variants[index].image = File(picked.path));
  }

  void _addVariant() {
    setState(() {
      _hasVariants = true;
      _variants.add(_VariantDraft());
    });
  }

  void _removeVariant(int index) {
    final removed = _variants.removeAt(index);
    removed.dispose();
    if (_variants.isEmpty) _hasVariants = false;
    setState(() {});
  }

  Widget _variantEditor() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: _fieldBorder, width: 1.2),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Modèles / couleurs', style: TextStyle(color: vendorText, fontSize: 14, fontWeight: FontWeight.w900)),
                    SizedBox(height: 3),
                    Text('Ajoutez les couleurs ou modèles sans ressaisir le poids, les dimensions et la description.', style: TextStyle(color: Color(0xFF667896), fontSize: 11.5, height: 1.35)),
                  ],
                ),
              ),
              Switch(
                value: _hasVariants,
                activeColor: vendorOrange,
                onChanged: (value) => setState(() {
                  _hasVariants = value;
                  if (value && _variants.isEmpty) _variants.add(_VariantDraft());
                }),
              ),
            ],
          ),
          if (_hasVariants) ...[
            const SizedBox(height: 12),
            ...List.generate(_variants.length, (index) {
              final variant = _variants[index];
              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFD),
                  border: Border.all(color: const Color(0xFFDCE5F0)),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Expanded(child: Text('Variante ${index + 1}', style: const TextStyle(fontWeight: FontWeight.w900, color: vendorText))),
                      IconButton(onPressed: () => _removeVariant(index), icon: const Icon(Icons.delete_outline_rounded, color: Color(0xFFB42318))),
                    ]),
                    const SizedBox(height: 6),
                    TextFormField(controller: variant.type, decoration: const InputDecoration(labelText: 'Type', hintText: 'Couleur, modèle, finition…')),
                    const SizedBox(height: 10),
                    TextFormField(controller: variant.value, decoration: const InputDecoration(labelText: 'Nom de la variante *', hintText: 'Ex. Bleu nuit')),
                    const SizedBox(height: 10),
                    Row(children: [
                      Expanded(child: TextFormField(controller: variant.stock, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Stock *'))),
                      const SizedBox(width: 10),
                      Expanded(child: TextFormField(controller: variant.price, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Prix différent', hintText: 'Optionnel'))),
                    ]),
                    const SizedBox(height: 10),
                    Row(children: [
                      Expanded(child: TextFormField(controller: variant.colorHex, decoration: const InputDecoration(labelText: 'Couleur HEX', hintText: '#1E5EFF'))),
                      const SizedBox(width: 10),
                      OutlinedButton.icon(
                        onPressed: () => _pickVariantImage(index),
                        icon: const Icon(Icons.image_outlined),
                        label: Text(variant.image != null || variant.imageUrl.isNotEmpty ? 'Changer photo' : 'Photo'),
                      ),
                    ]),
                    if (variant.image != null || variant.imageUrl.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: SizedBox(
                          width: 72,
                          height: 72,
                          child: variant.image != null
                              ? Image.file(variant.image!, fit: BoxFit.cover)
                              : Image.network(variant.imageUrl, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.broken_image_outlined)),
                        ),
                      ),
                    ],
                  ],
                ),
              );
            }),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _addVariant,
                icon: const Icon(Icons.add_rounded),
                label: const Text('Ajouter une couleur / un modèle'),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _mediaStep() {
    final videoName = _video?.path.split(Platform.pathSeparator).last;
    final canAdd = _images.length < 10;
    return _FormCard(
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            const Expanded(child: MkLabel('Images produit', required: true)),
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(
                '${_images.length}/10 images',
                style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
              ),
            ),
          ],
        ),
        _dropZone(
          icon: Icons.add_photo_alternate_rounded,
          title: 'Ajouter des photos',
          line1: canAdd ? 'Appuyez pour sélectionner vos images' : 'Vous avez atteint la limite de 10 images',
          line2: 'Format : JPG, PNG • Taille max : 5 Mo • Format carré recommandé (1:1)',
          orange: true,
          busy: _pickingImages,
          onTap: canAdd && !_pickingImages ? () => _addImages(ImageSource.gallery) : null,
        ),
        const SizedBox(height: 10),
        _OutlineAction(
          icon: Icons.photo_camera_rounded,
          label: 'Prendre une photo',
          onTap: canAdd && !_pickingImages ? () => _addImages(ImageSource.camera) : null,
        ),
        if (_images.isNotEmpty) ...[
          const SizedBox(height: 10),
          _imageGrid(),
        ],
        const SizedBox(height: 12),
        const MkInfoBox(
          icon: Icons.lightbulb_rounded,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Conseils pour de belles photos',
                style: TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w800),
              ),
              SizedBox(height: 4),
              _Tip('Utilisez des images claires et de bonne qualité'),
              _Tip('Montrez le produit sous plusieurs angles'),
              _Tip('Évitez les photos floues ou avec du texte'),
              _Tip('Le format carré (1:1) est fortement recommandé'),
            ],
          ),
        ),
        _gap,
        _labeled(
          'Vidéo produit',
          _dropZone(
            icon: Icons.videocam_rounded,
            title: videoName ?? 'Ajouter une vidéo',
            line1: videoName == null ? 'Appuyez pour sélectionner votre vidéo' : 'Appuyez pour remplacer la vidéo',
            line2: 'Format : MP4, MOV • Taille max : 50 Mo • Durée max : 2 minutes',
            orange: true,
            done: videoName != null,
            onTap: _pickVideo,
          ),
          note: '(optionnelle)',
        ),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(10)),
          child: const Row(
            children: [
              Icon(Icons.info_rounded, color: Mk.blue, size: 24),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Une vidéo permet de mieux présenter votre produit et d’augmenter vos ventes.',
                  style: TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
        ),
        _gap,
        _variantEditor(),
      ],
    );
  }

  /// Photos ajoutées, trois par ligne ; la première est l'image principale.
  Widget _imageGrid() {
    return LayoutBuilder(
      builder: (context, constraints) {
        const gap = 8.0;
        final size = (constraints.maxWidth - gap * 2) / 3;
        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [
            for (var index = 0; index < _images.length; index++)
              SizedBox(
                width: size,
                height: size,
                child: Stack(
                  children: [
                    Positioned.fill(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: Image.file(_images[index], fit: BoxFit.cover),
                      ),
                    ),
                    if (index == 0)
                      Positioned(
                        left: 4,
                        bottom: 6,
                        right: 4,
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(color: Mk.blue, borderRadius: BorderRadius.circular(99)),
                            child: const FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                'Image principale',
                                style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                              ),
                            ),
                          ),
                        ),
                      ),
                    Positioned(
                      top: 0,
                      right: 0,
                      child: IconButton(
                        tooltip: 'Retirer cette photo',
                        onPressed: () => setState(() => _images.removeAt(index)),
                        padding: const EdgeInsets.all(6),
                        constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
                        icon: Container(
                          width: 28,
                          height: 28,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                            boxShadow: [BoxShadow(color: Color(0x330A1B4F), blurRadius: 4)],
                          ),
                          child: const Icon(Icons.close_rounded, color: Mk.navy, size: 18),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }

  Widget _bottomActions() {
    final last = _step == 4;
    final creating = widget.productId == null;
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: _cardBorder)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          child: Row(
            children: [
              Expanded(
                child: last
                    ? _BottomButton(
                        icon: Icons.description_outlined,
                        label: 'Enregistrer en brouillon',
                        caption: 'Sauvegardez et terminez plus tard',
                        onTap: _saving ? null : () => _save(draft: true),
                      )
                    : _BottomButton(
                        label: _step == 0 ? 'Annuler' : 'Précédent',
                        orangeOutline: true,
                        onTap: _saving ? null : _previous,
                      ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: last
                    ? _BottomButton(
                        icon: Icons.send_outlined,
                        label: creating ? 'Publier le produit' : 'Enregistrer',
                        caption: creating ? 'Votre produit sera mis en ligne' : 'Vos modifications seront appliquées',
                        filled: true,
                        loading: _saving,
                        onTap: _saving ? null : () => _save(draft: false),
                      )
                    : _BottomButton(label: 'Suivant', filled: true, onTap: _saving ? null : _next),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Champs
  // ---------------------------------------------------------------------------

  /// Libellé (avec astérisque, mention « optionnel » ou compteur), champ et aide.
  Widget _labeled(
    String label,
    Widget field, {
    bool required = false,
    bool optional = false,
    String? note,
    String? help,
    Widget? counter,
  }) {
    final suffix = note ?? (optional ? '(optionnel)' : null);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Text.rich(
                  TextSpan(
                    text: label,
                    children: [
                      if (required)
                        const TextSpan(text: ' *', style: TextStyle(color: Mk.orange, fontWeight: FontWeight.w800)),
                      if (suffix != null)
                        TextSpan(
                          text: ' $suffix',
                          style: const TextStyle(color: Mk.muted, fontWeight: FontWeight.w500),
                        ),
                    ],
                  ),
                  style: const TextStyle(color: Mk.navy, fontSize: 13.5, fontWeight: FontWeight.w700),
                ),
              ),
              if (counter != null) counter,
            ],
          ),
        ),
        field,
        if (help != null)
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(help, style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
          ),
      ],
    );
  }

  /// Compteur « 12/200 » mis à jour pendant la saisie.
  Widget _counter(String key, int max) {
    return ValueListenableBuilder<TextEditingValue>(
      valueListenable: ctrl(key),
      builder: (context, value, _) => Text(
        '${value.text.characters.length}/$max',
        style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500),
      ),
    );
  }

  /// En-tête interne d'une carte : pictogramme, titre et phrase d'introduction.
  Widget _cardHeading(IconData icon, String title, String subtitle) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: Mk.blue, size: 24),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(color: Mk.navy, fontSize: 19, fontWeight: FontWeight.w800, letterSpacing: -.3)),
              const SizedBox(height: 2),
              Text(subtitle, style: const TextStyle(color: Mk.muted, fontSize: 13, height: 1.35, fontWeight: FontWeight.w500)),
            ],
          ),
        ),
      ],
    );
  }

  /// Choix Oui / Non en deux segments.
  Widget _toggleRow(String title, bool value, ValueChanged<bool> onChanged, {String? subtitle}) {
    Widget segment(String label, bool selected, bool yes) => Expanded(
          child: Semantics(
            button: true,
            selected: selected,
            label: '$title : $label',
            excludeSemantics: true,
            child: InkWell(
              onTap: () => onChanged(yes),
              borderRadius: BorderRadius.circular(9),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 140),
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  gradient: selected && yes ? Mk.orangeGradient : null,
                  color: selected && !yes ? const Color(0xFF8E9BBD) : null,
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Text(
                  label,
                  style: TextStyle(
                    color: selected ? Colors.white : Mk.muted,
                    fontSize: 14,
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  ),
                ),
              ),
            ),
          ),
        );
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w700)),
              if (subtitle != null)
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Text(subtitle, style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
                ),
            ],
          ),
        ),
        const SizedBox(width: 10),
        Container(
          width: 136,
          padding: const EdgeInsets.all(3),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _fieldBorder, width: 1.2),
          ),
          child: Row(children: [segment('Oui', value, true), segment('Non', !value, false)]),
        ),
      ],
    );
  }

  Widget _textField(
    String key,
    String hint, {
    bool required = false,
    String requiredMessage = 'Obligatoire',
    bool numeric = false,
    int? maxLength,
    int minLines = 1,
    int maxLines = 1,
  }) {
    return TextFormField(
      controller: ctrl(key),
      keyboardType: numeric
          ? const TextInputType.numberWithOptions(decimal: true)
          : (maxLines > 1 ? TextInputType.multiline : TextInputType.text),
      textCapitalization: numeric ? TextCapitalization.none : TextCapitalization.sentences,
      maxLength: maxLength,
      minLines: minLines,
      maxLines: maxLines,
      validator: required ? (v) => (v ?? '').trim().isEmpty ? requiredMessage : null : null,
      style: _fieldText,
      // Un champ d'une ligne garde son exemple sur une ligne, quitte à le tronquer.
      decoration: _inputDecoration(hint).copyWith(hintMaxLines: maxLines > 1 ? 3 : 1),
    );
  }

  /// Champ numérique suivi de son unité (FCFA, kg, cm).
  Widget _unitField(
    String key,
    String hint,
    String unit, {
    bool required = false,
    ValueChanged<String>? onChanged,
  }) {
    return TextFormField(
      controller: ctrl(key),
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      onChanged: onChanged,
      validator: required ? (v) => (v ?? '').trim().isEmpty ? 'Obligatoire' : null : null,
      style: _fieldText,
      decoration: _inputDecoration(hint).copyWith(
        suffixIcon: Padding(
          padding: const EdgeInsets.only(right: 12, left: 4),
          child: Text(unit, style: const TextStyle(color: Mk.icon, fontSize: 13.5, fontWeight: FontWeight.w500)),
        ),
        suffixIconConstraints: const BoxConstraints(minWidth: 0, minHeight: 0),
      ),
    );
  }

  static const _fieldText = TextStyle(fontFamily: Mk.font, color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w600, letterSpacing: 0);

  InputDecoration _inputDecoration(String hint) {
    OutlineInputBorder border(Color color, [double width = 1.2]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: color, width: width),
        );
    return InputDecoration(
      hintText: hint,
      hintMaxLines: 3,
      hintStyle: const TextStyle(fontFamily: Mk.font, color: Mk.hint, fontSize: 14, fontWeight: FontWeight.w500, letterSpacing: 0),
      filled: true,
      fillColor: _fieldFill,
      isDense: true,
      counterText: '',
      errorMaxLines: 2,
      errorStyle: const TextStyle(fontFamily: Mk.font, color: Mk.danger, fontSize: 12, fontWeight: FontWeight.w600),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: border(_fieldBorder),
      enabledBorder: border(_fieldBorder),
      focusedBorder: border(Mk.blue, 1.6),
      errorBorder: border(Mk.danger),
      focusedErrorBorder: border(Mk.danger, 1.6),
    );
  }

  /// Champ qui ouvre une liste de choix.
  Widget _selector<T>({
    required String title,
    required T? value,
    required Map<T, String> items,
    required String hint,
    required ValueChanged<T> onChanged,
    bool enabled = true,
  }) {
    final label = value == null ? null : items[value];
    return Semantics(
      button: true,
      enabled: enabled,
      label: '$title : ${label ?? hint}',
      excludeSemantics: true,
      child: Opacity(
        opacity: enabled ? 1 : .55,
        child: Material(
          color: _fieldFill,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: const BorderSide(color: _fieldBorder, width: 1.2),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            focusColor: Colors.transparent,
            onTap: !enabled
                ? null
                : () async {
                    final picked = await showVendorSelect<T>(
                      context,
                      title: title,
                      items: items.keys.toList(),
                      label: (v) => items[v] ?? '$v',
                      selected: value,
                    );
                    if (picked != null) onChanged(picked);
                  },
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 48),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 6, 8, 6),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        label ?? hint,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: label != null
                            ? _fieldText
                            : const TextStyle(color: Mk.hint, fontSize: 14, fontWeight: FontWeight.w500),
                      ),
                    ),
                    const Icon(Icons.keyboard_arrow_down_rounded, color: Mk.navy, size: 24),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _categorySelector({
    required String label,
    required int? value,
    required List<Map<String, dynamic>> items,
    required String hint,
    required ValueChanged<int> onChanged,
    bool enabled = true,
  }) {
    final options = <int, String>{
      for (final item in items)
        if (int.tryParse('${item['id']}') != null) int.parse('${item['id']}'): '${item['name'] ?? 'Catégorie'}',
    };
    return _labeled(
      label,
      _selector<int>(title: label, value: value, items: options, hint: hint, onChanged: onChanged, enabled: enabled),
      required: true,
    );
  }

  /// Zone d'ajout en pointillés (photos, vidéo, fiche technique).
  Widget _dropZone({
    required IconData icon,
    required String title,
    required String line1,
    required String line2,
    required VoidCallback? onTap,
    bool orange = false,
    bool done = false,
    bool busy = false,
  }) {
    final accent = done ? Mk.success : (orange ? Mk.orange : Mk.icon);
    return Semantics(
      button: true,
      label: title,
      child: CustomPaint(
        foregroundPainter: _DashedBorderPainter(
          color: done ? Mk.success : (orange ? Mk.orange : const Color(0xFFB4C0D8)),
          radius: 12,
        ),
        child: Material(
          color: done ? Mk.successSoft : (orange ? Mk.softOrange : Colors.white),
          borderRadius: BorderRadius.circular(12),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
              child: Column(
                children: [
                  if (busy)
                    const SizedBox.square(
                      dimension: 30,
                      child: CircularProgressIndicator(strokeWidth: 2.6, color: Mk.orange),
                    )
                  else
                    Icon(done ? Icons.check_circle_rounded : icon, color: orange && !done ? Mk.orange : accent, size: 34),
                  const SizedBox(height: 6),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: orange && !done ? Mk.orange : Mk.navy,
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    line1,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    line2,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Mk.muted, fontSize: 11.5, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _twoColumns(Widget left, Widget right) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: left),
          const SizedBox(width: 10),
          Expanded(child: right),
        ],
      );
}

const _cardBorder = Color(0xFFE4E9F1);
const _fieldBorder = Color(0xFFD9E0EC);
const _fieldFill = Color(0xFFF3F6FB);

/// En-tête blanc : croix de fermeture, titre centré et logo.
class _WizardHeader extends StatelessWidget {
  const _WizardHeader({required this.title, required this.onClose});

  final String title;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: _cardBorder)),
      ),
      child: SafeArea(
        bottom: false,
        child: SizedBox(
          height: 56,
          child: Row(
            children: [
              const SizedBox(width: 4),
              IconButton(
                onPressed: onClose,
                tooltip: 'Fermer',
                icon: const Icon(Icons.close_rounded, color: Mk.navy, size: 28),
              ),
              Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    title,
                    maxLines: 1,
                    style: const TextStyle(color: Mk.navy, fontSize: 19, fontWeight: FontWeight.w800, letterSpacing: -.3),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(left: 6, right: 14),
                child: Image.asset('assets/images/mockups/logo_navy.png', height: 26, semanticLabel: 'OVANIE Vendeur'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Barres de progression, « Étape X sur 5 » et noms des étapes.
class _StepProgress extends StatelessWidget {
  const _StepProgress({required this.step, required this.titles, required this.onSelect});

  final int step;
  final List<String> titles;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            for (var index = 0; index < titles.length; index++) ...[
              if (index > 0) const SizedBox(width: 6),
              Expanded(
                child: Container(
                  height: 12,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    gradient: index <= step ? Mk.orangeGradient : null,
                    color: index <= step ? null : const Color(0xFFDDE2EB),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: index < step ? const Icon(Icons.check_rounded, color: Colors.white, size: 12) : null,
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 8),
        Text(
          'Étape ${step + 1} sur ${titles.length} · ${titles[step]}',
          style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
        ),
        const SizedBox(height: 4),
        LayoutBuilder(
          builder: (context, box) {
            final labels = Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (var index = 0; index < titles.length; index++) ...[
                  if (index > 0)
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 7),
                      child: Text('·', style: TextStyle(color: Mk.muted, fontSize: 16, fontWeight: FontWeight.w800)),
                    ),
                  Semantics(
                    button: index < step,
                    selected: index == step,
                    child: InkWell(
                      onTap: index < step ? () => onSelect(index) : null,
                      borderRadius: BorderRadius.circular(6),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 2),
                        child: Text(
                          titles[index],
                          style: TextStyle(
                            color: index == step ? Mk.orange : (index < step ? Mk.blueDeep : Mk.muted),
                            fontSize: 13.5,
                            fontWeight: index == step ? FontWeight.w700 : FontWeight.w500,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ],
            );
            // Téléphone courant : les cinq noms tiennent sur la ligne, légèrement réduits.
            if (box.maxWidth >= 370) {
              return FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: labels);
            }
            // Écran étroit : la ligne défile et montre la fin à partir de l'étape 4.
            return SingleChildScrollView(scrollDirection: Axis.horizontal, reverse: step >= 3, child: labels);
          },
        ),
      ],
    );
  }
}

/// Bandeau illustré en tête de chaque étape.
class _StepBanner extends StatelessWidget {
  const _StepBanner({required this.step, required this.title, required this.subtitle});

  final int step;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 8, 12, 8),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          Expanded(
            flex: 10,
            child: Image.asset(
              'assets/images/mockups/form_step${step + 1}.jpg',
              height: 92,
              fit: BoxFit.contain,
              excludeFromSemantics: true,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            flex: 14,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: const TextStyle(color: Mk.navy, fontSize: 18, height: 1.15, fontWeight: FontWeight.w800, letterSpacing: -.3),
                ),
                const SizedBox(height: 4),
                Text(subtitle, style: const TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte blanche qui regroupe des champs.
class _FormCard extends StatelessWidget {
  const _FormCard({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: children),
    );
  }
}

/// Bouton à contour orange sur toute la largeur (« Prendre une photo »).
class _OutlineAction extends StatelessWidget {
  const _OutlineAction({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onTap == null ? .5 : 1,
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: const BorderSide(color: Mk.orange, width: 1.4),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: SizedBox(
            height: 46,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, color: Mk.orange, size: 24),
                const SizedBox(width: 10),
                Text(label, style: const TextStyle(color: Mk.orange, fontSize: 15, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Bouton de la barre du bas : contour ou plein, avec légende facultative.
class _BottomButton extends StatelessWidget {
  const _BottomButton({
    required this.label,
    required this.onTap,
    this.icon,
    this.caption,
    this.filled = false,
    this.orangeOutline = false,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onTap;
  final IconData? icon;
  final String? caption;
  final bool filled;
  final bool orangeOutline;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final foreground = filled ? Colors.white : (orangeOutline ? const Color(0xFFE85400) : Mk.navy);
    final texts = Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: caption == null ? CrossAxisAlignment.center : CrossAxisAlignment.start,
      children: [
        Text(
          label,
          maxLines: 1,
          style: TextStyle(fontFamily: Mk.font, color: foreground, fontSize: caption == null ? 15.5 : 13.5, fontWeight: FontWeight.w700),
        ),
        if (caption != null)
          Text(
            caption!,
            maxLines: 1,
            style: TextStyle(
              fontFamily: Mk.font,
              color: filled ? Colors.white : Mk.muted,
              fontSize: 10.5,
              fontWeight: FontWeight.w500,
            ),
          ),
      ],
    );
    return Semantics(
      button: true,
      enabled: onTap != null,
      label: label,
      excludeSemantics: true,
      child: Opacity(
        opacity: onTap == null && !loading ? .55 : 1,
        child: Container(
          height: 50,
          decoration: BoxDecoration(
            gradient: filled ? Mk.orangeGradient : null,
            color: filled ? null : Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: filled ? null : Border.all(color: orangeOutline ? Mk.orange : Mk.icon, width: 1.3),
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: onTap,
              borderRadius: BorderRadius.circular(10),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Center(
                  child: loading
                      ? const SizedBox.square(
                          dimension: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                        )
                      : FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              if (icon != null) ...[
                                Icon(icon, color: foreground, size: 24),
                                const SizedBox(width: 8),
                              ],
                              texts,
                            ],
                          ),
                        ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Ligne à puce d'un encadré de conseils.
class _Tip extends StatelessWidget {
  const _Tip(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 5,
            height: 5,
            margin: const EdgeInsets.only(top: 7, right: 10, left: 2),
            decoration: const BoxDecoration(color: Mk.blue, shape: BoxShape.circle),
          ),
          Expanded(child: Text(text)),
        ],
      ),
    );
  }
}

class _DashedBorderPainter extends CustomPainter {
  const _DashedBorderPainter({required this.color, required this.radius});
  final Color color;
  final double radius;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.3;
    final path = Path()
      ..addRRect(
        RRect.fromRectAndRadius((Offset.zero & size).deflate(.6), Radius.circular(radius)),
      );
    for (final metric in path.computeMetrics()) {
      double distance = 0;
      while (distance < metric.length) {
        final next = (distance + 6).clamp(0, metric.length).toDouble();
        canvas.drawPath(metric.extractPath(distance, next), paint);
        distance += 10;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashedBorderPainter oldDelegate) =>
      oldDelegate.color != color || oldDelegate.radius != radius;
}

class _FormLoading extends StatelessWidget {
  const _FormLoading();
  @override
  Widget build(BuildContext context) {
    return const Center(child: CircularProgressIndicator(color: Mk.orange));
  }
}

class _VariantDraft {
  final int? id;
  final TextEditingController type;
  final TextEditingController value;
  final TextEditingController stock;
  final TextEditingController price;
  final TextEditingController colorHex;
  String imageUrl;
  File? image;

  _VariantDraft({
    this.id,
    String type = 'Couleur',
    String value = '',
    String stock = '0',
    String price = '',
    String colorHex = '',
    this.imageUrl = '',
    this.image,
  })  : type = TextEditingController(text: type),
        value = TextEditingController(text: value),
        stock = TextEditingController(text: stock),
        price = TextEditingController(text: price),
        colorHex = TextEditingController(text: colorHex);

  factory _VariantDraft.fromMap(Map<String, dynamic> map) => _VariantDraft(
        id: int.tryParse('${map['id'] ?? ''}'),
        type: '${map['type'] ?? 'Couleur'}',
        value: '${map['value'] ?? ''}',
        stock: '${map['stock'] ?? '0'}',
        price: map['price_override'] == null ? '' : '${map['price_override']}',
        colorHex: '${map['color_hex'] ?? ''}',
        imageUrl: '${map['image_url'] ?? map['card_url'] ?? ''}',
      );

  void dispose() {
    type.dispose();
    value.dispose();
    stock.dispose();
    price.dispose();
    colorHex.dispose();
  }
}
