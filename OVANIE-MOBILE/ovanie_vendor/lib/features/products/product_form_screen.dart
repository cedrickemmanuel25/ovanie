import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../data/vendor_repository.dart';
import 'product_ui.dart';

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
        'technical_details',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'unloading_instructions',
      ]) {
        ctrl(key, _existing[key]);
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
  Future<void> _pickImages() async {
    if (_pickingImages || _images.length >= 10) return;
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Prendre une photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choisir dans la galerie'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source != null && mounted) await _addImages(source);
  }

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
      setState(
        () => _images.addAll(
          picked.take(10 - _images.length).map((item) => File(item.path)),
        ),
      );
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
      if (_productType == null)
        return _showValidation('Sélectionnez le type de produit.');
      if (!_productStates.containsKey(_productState))
        return _showValidation('Sélectionnez l’état du produit.');
    }
    if (_step == 1) {
      if (_unit == null)
        return _showValidation('Sélectionnez l’unité de vente.');
      if (_saleType == null)
        return _showValidation('Sélectionnez le mode de vente.');
    }
    if (_step == 2) {
      if (_usage == null)
        return _showValidation('Sélectionnez l’usage recommandé.');
      if (_warranty == null)
        return _showValidation('Sélectionnez la durée de garantie.');
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

  void _previous() {
    FocusManager.instance.primaryFocus?.unfocus();
    if (_saving) return;
    if (_step == 0) {
      Navigator.maybePop(context);
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
          _productType == null ||
          ctrl('short_description').text.trim().isEmpty ||
          ctrl('description').text.trim().isEmpty ||
          ctrl('price').text.trim().isEmpty ||
          ctrl('stock').text.trim().isEmpty ||
          _unit == null ||
          ctrl('min_order_quantity').text.trim().isEmpty ||
          _saleType == null ||
          _usage == null ||
          ctrl('technical_details').text.trim().isEmpty ||
          _warranty == null ||
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
        // Seuils calculés côté app, jamais saisis par le vendeur : toujours
        // recalculés à partir du prix courant pour ne jamais rester figés à
        // une valeur devenue >= au prix si celui-ci est baissé ensuite
        // (price_p1 doit toujours rester strictement inférieur à price).
        final price = double.tryParse(
          ctrl('price').text.trim().replaceAll(',', '.'),
        );
        if (price != null && price > 0) {
          values['price_p1'] = (price * 0.95).round();
          values['price_p2'] = (price * 0.90).round();
          values['price_p3'] = (price * 0.85).round();
        }
      }

      await _repo.saveProduct(
        values,
        productId: widget.productId,
        images: _images,
        technicalSheet: _technicalSheet,
        video: _video,
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFD),
      body: Column(
        children: [
          _WizardHeader(
            title: widget.productId == null
                ? 'Ajouter un produit'
                : 'Modifier un produit',
            subtitle:
                'Mettez vos produits en ligne et\ntouchez plus de clients.',
            onBack: _previous,
          ),
          Expanded(
            child: _loading
                ? const _FormLoading()
                : ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      Container(
                        padding: const EdgeInsets.fromLTRB(14, 12, 14, 22),
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.vertical(
                            top: Radius.circular(24),
                          ),
                        ),
                        child: Column(
                          children: [
                            _StepProgress(currentStep: _step),
                            const SizedBox(height: 12),
                            Form(
                              key: _forms[_step],
                              child: _StepCard(
                                icon: _stepIcon,
                                title: _stepTitle,
                                subtitle: _stepSubtitle,
                                child: _currentStep(),
                              ),
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 12),
                              VendorErrorBox(_error),
                            ],
                            if (_step == 2) ...[
                              const SizedBox(height: 12),
                              const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.shield_outlined,
                                    color: vendorText,
                                    size: 18,
                                  ),
                                  SizedBox(width: 7),
                                  Flexible(
                                    child: Text(
                                      'Les informations fournies seront visibles sur votre fiche produit.',
                                      style: TextStyle(
                                        color: Color(0xFF536C98),
                                        fontSize: 11.5,
                                      ),
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
          ),
        ],
      ),
    );
  }

  IconData get _stepIcon => const [
    Icons.description_outlined,
    Icons.monetization_on_outlined,
    Icons.settings_outlined,
    Icons.local_shipping_outlined,
    Icons.image_outlined,
  ][_step];
  String get _stepTitle => const [
    'Informations du produit',
    'Prix & unité',
    'Détail technique',
    'Logistique',
    'Médias du produit',
  ][_step];
  String get _stepSubtitle => const [
    'Renseignez les informations générales de votre produit\npour commencer.',
    'Définissez le prix, l’unité de vente et la quantité\nde votre produit.',
    'Ajoutez les caractéristiques techniques de votre\nproduit pour mieux informer vos clients.',
    'Renseignez les informations logistiques de votre\nproduit pour le calcul de la livraison.',
    'Ajoutez des photos et une vidéo pour rendre votre produit\nplus attractif.',
  ][_step];

  Widget _currentStep() {
    return switch (_step) {
      0 => _informationStep(),
      1 => _priceStep(),
      2 => _technicalStep(),
      3 => _logisticsStep(),
      _ => _mediaStep(),
    };
  }

  Widget _informationStep() {
    final roots = _categoryTree;
    final children = _childrenFor(_mainCategoryId);
    return Column(
      children: [
        _fieldTitle('Nom du produit', required: true),
        _textField(
          'name',
          'Ex. Ciment CPA 42.5R',
          Icons.inventory_2_outlined,
          required: true,
        ),
        const SizedBox(height: 10),
        _categorySelector(
          label: 'Catégorie principale',
          value: _mainCategoryId,
          items: roots,
          hint: 'Sélectionnez une catégorie',
          required: true,
          onChanged: (id) {
            setState(() {
              _mainCategoryId = id;
              _subcategoryId = null;
            });
          },
        ),
        const SizedBox(height: 10),
        _categorySelector(
          label: 'Sous-catégorie',
          value: _subcategoryId,
          items: children,
          hint: 'Sélectionnez une sous-catégorie',
          required: true,
          enabled: _mainCategoryId != null && children.isNotEmpty,
          onChanged: (id) {
            setState(() => _subcategoryId = id);
          },
        ),
        const SizedBox(height: 10),
        _pair(
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Marque', suffix: '(optionnel)'),
              _textField(
                'brand',
                'Ex. CIMAF, Sika, Hilti...',
                Icons.sell_outlined,
              ),
            ],
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Type de produit', required: true),
              _selector<String>(
                value: _productType,
                items: _productTypes,
                hint: 'Sélectionnez un type',
                icon: Icons.inventory_2_outlined,
                onChanged: (v) => setState(() => _productType = v),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        _fieldTitle('État du produit', required: true),
        _selector<String>(
          value: _productState,
          items: _productStates,
          hint: 'Sélectionnez l’état du produit',
          icon: Icons.verified_outlined,
          onChanged: (v) => setState(() => _productState = v ?? 'new'),
        ),
        const SizedBox(height: 10),
        _fieldTitle('Description courte', required: true),
        _counterField(
          'short_description',
          'Une brève description de votre produit',
          Icons.description_outlined,
          160,
          required: true,
          maxLines: 1,
        ),
        const SizedBox(height: 10),
        _fieldTitle('Mots-clés', suffix: '(optionnel)'),
        _textField(
          'keywords',
          'Ex. ciment, construction, BTP, qualité...',
          Icons.tag_rounded,
        ),
        const SizedBox(height: 4),
        const Align(
          alignment: Alignment.centerLeft,
          child: Text(
            'Séparez les mots-clés par une virgule ( , )',
            style: TextStyle(color: Color(0xFF536C98), fontSize: 11),
          ),
        ),
        const SizedBox(height: 10),
        _fieldTitle('Description détaillée', required: true),
        _richDescription(),
        const SizedBox(height: 16),
        _wizardButtons(firstLabel: 'Annuler'),
      ],
    );
  }

  Widget _priceStep() {
    return Column(
      children: [
        _pair(
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Prix normal', required: true),
              _moneyField(
                'price',
                'Ex. : 6 500',
                Icons.monetization_on_outlined,
                required: true,
              ),
              const SizedBox(height: 4),
              const Text(
                'Prix de vente habituel de votre produit.',
                style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
              ),
            ],
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Prix promo', suffix: '(optionnel)'),
              _moneyField('promo_price', 'Ex. : 5 900', Icons.sell_outlined),
              const SizedBox(height: 4),
              const Text(
                'Prix réduit pendant une période limitée.',
                style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Align(
          alignment: Alignment.centerLeft,
          child: SizedBox(
            width: MediaQuery.sizeOf(context).width * .48,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _fieldTitle('Stock disponible', required: true),
                _textField(
                  'stock',
                  'Ex. : 100',
                  Icons.inventory_2_outlined,
                  required: true,
                  numeric: true,
                ),
                const SizedBox(height: 4),
                const Text(
                  'Nombre d’unités disponibles en stock.',
                  style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        _pair(
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Unité de vente', required: true),
              ProductSheetSelector<String>(
                value: _unit,
                label: 'Unité de vente',
                items: _units,
                display: _unitLabel,
                hint: 'Sélectionnez une unité',
                icon: Icons.inventory_2_outlined,
                onChanged: (v) => setState(() => _unit = v),
              ),
              const SizedBox(height: 4),
              const Text(
                'Ex. : Pièce, Sac, Carton, Kg, Litre, m², etc.',
                style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
              ),
            ],
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Libellé personnel', suffix: '(optionnel)'),
              _textField(
                'unit_label',
                'Ex. : Sac de 50 kg',
                Icons.sell_outlined,
              ),
              const SizedBox(height: 4),
              const Text(
                'Nom personnalisé de l’unité de vente.',
                style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Align(
          alignment: Alignment.centerLeft,
          child: SizedBox(
            width: MediaQuery.sizeOf(context).width * .48,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _fieldTitle('Quantité minimum', required: true),
                _textField(
                  'min_order_quantity',
                  'Ex. : 1',
                  Icons.shopping_cart_outlined,
                  required: true,
                  numeric: true,
                ),
                const SizedBox(height: 4),
                const Text(
                  'Quantité minimale que le client peut commander.',
                  style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        _fieldTitle('Mode de vente', required: true),
        _selector<String>(
          value: _saleType,
          items: _saleTypes,
          hint: 'Sélectionnez un mode de vente',
          icon: Icons.grid_view_rounded,
          onChanged: (v) => setState(() => _saleType = v),
        ),
        const SizedBox(height: 4),
        const Align(
          alignment: Alignment.centerLeft,
          child: Text(
            'Ex. : Vente à l’unité, par lot, au poids, au mètre, etc.',
            style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
          ),
        ),
        const SizedBox(height: 14),
        _yesNo(
          'Prix négociable',
          _negotiable,
          Icons.handshake_outlined,
          (v) => setState(() => _negotiable = v),
        ),
        const SizedBox(height: 4),
        const Align(
          alignment: Alignment.centerLeft,
          child: Text(
            'Le client pourra vous proposer un prix depuis la fiche produit.',
            style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
          ),
        ),
        const SizedBox(height: 22),
        _wizardButtons(),
      ],
    );
  }

  Widget _technicalStep() {
    return Column(
      children: [
        _fieldTitle('Usage recommandé', required: true),
        _selector<String>(
          value: _usage,
          items: _usages,
          hint: 'Sélectionnez l’usage recommandé',
          icon: Icons.inventory_2_outlined,
          onChanged: (v) => setState(() => _usage = v),
        ),
        const SizedBox(height: 4),
        const Align(
          alignment: Alignment.centerLeft,
          child: Text(
            'Ex. : Construction, Rénovation, Intérieur, Extérieur, etc.',
            style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
          ),
        ),
        const SizedBox(height: 14),
        _fieldTitle('Détails technique', required: true),
        _counterField(
          'technical_details',
          'Ex. :\n•  Matière / Composition\n•  Dimensions\n•  Poids\n•  Couleur\n•  Normes\n•  Autres spécifications techniques',
          Icons.description_outlined,
          1000,
          required: true,
          maxLines: 7,
        ),
        const SizedBox(height: 14),
        _fieldTitle('Garantie', required: true),
        _selector<String>(
          value: _warranty,
          items: _warranties,
          hint: 'Sélectionnez la durée de garantie',
          icon: Icons.shield_outlined,
          onChanged: (v) => setState(() => _warranty = v),
        ),
        const SizedBox(height: 4),
        const Align(
          alignment: Alignment.centerLeft,
          child: Text(
            'Ex. : 6 mois, 1 an, 2 ans, etc.',
            style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
          ),
        ),
        const SizedBox(height: 14),
        _fieldTitle('Fiche technique PDF', suffix: '(optionnel)'),
        _uploadBox(
          icon: Icons.cloud_upload_outlined,
          leadingIcon: Icons.picture_as_pdf_outlined,
          title: _technicalSheet == null
              ? 'Ajouter la fiche technique'
              : _technicalSheet!.path.split(Platform.pathSeparator).last,
          subtitle: 'Glissez-déposez votre fichier ici ou appuyez pour choisir',
          details: 'Format : PDF uniquement • Taille maximale : 5 Mo',
          onTap: _pickTechnicalSheet,
        ),
        const SizedBox(height: 22),
        _wizardButtons(),
      ],
    );
  }

  Widget _logisticsStep() {
    return Column(
      children: [
        _fieldTitle('Poids', required: true),
        _unitField(
          'weight_kg',
          'Ex. : 2.5',
          Icons.scale_outlined,
          'kg',
          required: true,
        ),
        const SizedBox(height: 14),
        _fieldColumns([
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Longueur', required: true),
              _unitField(
                'length_cm',
                'Ex. : 30',
                Icons.straighten_rounded,
                'cm',
                required: true,
                onChanged: (_) => setState(() {}),
              ),
            ],
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Largeur', required: true),
              _unitField(
                'width_cm',
                'Ex. : 20',
                Icons.inventory_2_outlined,
                'cm',
                required: true,
                onChanged: (_) => setState(() {}),
              ),
            ],
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _fieldTitle('Hauteur', required: true),
              _unitField(
                'height_cm',
                'Ex. : 15',
                Icons.height_rounded,
                'cm',
                required: true,
                onChanged: (_) => setState(() {}),
              ),
            ],
          ),
        ]),
        const SizedBox(height: 14),
        _fieldTitle('Volume', suffix: '(calculé auto)'),
        Container(
          height: 54,
          padding: const EdgeInsets.symmetric(horizontal: 15),
          decoration: BoxDecoration(
            color: const Color(0xFFF8FAFD),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFC7D4E7)),
          ),
          child: Row(
            children: [
              const Icon(
                Icons.inventory_2_outlined,
                color: Color(0xFF687B9D),
                size: 22,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  _volumeM3.toStringAsFixed(3),
                  style: const TextStyle(
                    color: Color(0xFF7183A7),
                    fontSize: 15,
                  ),
                ),
              ),
              const Text(
                'm³',
                style: TextStyle(color: Color(0xFF536C98), fontSize: 14),
              ),
            ],
          ),
        ),
        const SizedBox(height: 4),
        const Align(
          alignment: Alignment.centerLeft,
          child: Text(
            'Le volume est calculé automatiquement à partir des dimensions.',
            style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
          ),
        ),
        const SizedBox(height: 14),
        _yesNo(
          'Produit fragile',
          _fragile,
          Icons.wine_bar_outlined,
          (v) => setState(() => _fragile = v),
        ),
        const SizedBox(height: 14),
        _yesNo(
          'Déchargement requis',
          _unloading,
          Icons.handyman_outlined,
          (v) => setState(() => _unloading = v),
        ),
        if (_unloading) ...[
          const SizedBox(height: 10),
          _fieldTitle('Détails du déchargement', required: true),
          _textField(
            'unloading_instructions',
            'Ex. : chariot ou aide au déchargement nécessaire',
            Icons.local_shipping_outlined,
            required: true,
          ),
        ],
        const SizedBox(height: 14),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFFEAF4FF),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFB9DBFF)),
          ),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.info_outline_rounded,
                color: Color(0xFF0875E1),
                size: 31,
              ),
              SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Ces informations nous permettent de :',
                      style: TextStyle(
                        color: vendorText,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Calculer les frais de livraison et de sélectionner le mode logistique\nle plus adapté à votre produit.',
                      style: TextStyle(
                        color: Color(0xFF536C98),
                        fontSize: 11.5,
                        height: 1.3,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        _wizardButtons(),
      ],
    );
  }

  Widget _mediaStep() {
    return Column(
      children: [
        Row(
          children: [
            Expanded(child: _fieldTitle('Images produit', required: true)),
            Text(
              '${_images.length}/10 images',
              style: const TextStyle(color: Color(0xFF536C98), fontSize: 11.5),
            ),
          ],
        ),
        _uploadBox(
          icon: Icons.cloud_upload_outlined,
          title: 'Ajouter des photos',
          subtitle:
              'Glissez-déposez vos images ici ou appuyez pour sélectionner',
          details:
              'Format : JPG, PNG • Taille max : 5 Mo • Format carré recommandé (1:1)',
          onTap: _pickImages,
        ),
        const SizedBox(height: 10),
        _imageSlots(),
        const SizedBox(height: 10),
        OutlinedButton.icon(
          onPressed: _pickingImages || _images.length >= 10
              ? null
              : () => _addImages(ImageSource.camera),
          icon: const Icon(Icons.photo_camera_outlined),
          label: const Text('Prendre une photo'),
        ),
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFEAF3FF),
            borderRadius: BorderRadius.circular(8),
          ),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.info_rounded, color: Color(0xFF0875E1), size: 31),
              SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Conseils pour de belles photos',
                      style: TextStyle(
                        color: vendorText,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      '•  Utilisez des images claires et de bonne qualité\n•  Montrez le produit sous plusieurs angles\n•  Évitez les photos floues ou avec du texte\n•  Le format carré (1:1) est fortement recommandé',
                      style: TextStyle(
                        color: Color(0xFF536C98),
                        fontSize: 11.3,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const Divider(color: productBorder),
        const SizedBox(height: 10),
        _fieldTitle('Vidéo produit', suffix: '(optionnelle)'),
        _uploadBox(
          icon: Icons.ondemand_video_outlined,
          title: _video == null
              ? 'Ajouter une vidéo'
              : _video!.path.split(Platform.pathSeparator).last,
          subtitle:
              'Glissez-déposez votre vidéo ici ou appuyez pour sélectionner',
          details:
              'Format : MP4, MOV • Taille max : 50 Mo • Durée max : 2 minutes',
          onTap: _pickVideo,
        ),
        const SizedBox(height: 10),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
          decoration: BoxDecoration(
            color: const Color(0xFFF0F5FC),
            borderRadius: BorderRadius.circular(8),
          ),
          child: const Row(
            children: [
              Icon(Icons.videocam_rounded, color: Color(0xFF4E6497), size: 22),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Une vidéo permet de mieux présenter votre produit et d’augmenter vos ventes.',
                  style: TextStyle(color: Color(0xFF536C98), fontSize: 10.8),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: SizedBox(
                height: 64,
                child: OutlinedButton.icon(
                  onPressed: _saving ? null : () => _save(draft: true),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Color(0xFFB8C6DC)),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  icon: const Icon(
                    Icons.description_outlined,
                    color: vendorText,
                    size: 25,
                  ),
                  label: const FittedBox(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Enregistrer en brouillon',
                          style: TextStyle(
                            color: vendorText,
                            fontSize: 13,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          'Sauvegardez et terminez plus tard',
                          style: TextStyle(
                            color: Color(0xFF536C98),
                            fontSize: 9.7,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: SizedBox(
                height: 64,
                child: FilledButton.icon(
                  onPressed: _saving ? null : () => _save(draft: false),
                  style: FilledButton.styleFrom(
                    backgroundColor: productOrange,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  icon: _saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(
                          Icons.send_rounded,
                          color: Colors.white,
                          size: 25,
                        ),
                  label: const FittedBox(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Publier le produit',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          'Votre produit sera mis en ligne',
                          style: TextStyle(color: Colors.white, fontSize: 9.7),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _fieldTitle(String label, {bool required = false, String? suffix}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Text(
            label,
            style: const TextStyle(
              color: vendorText,
              fontSize: 13.5,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (suffix != null) ...[
            const SizedBox(width: 4),
            Text(
              suffix,
              style: const TextStyle(
                color: vendorText,
                fontSize: 13,
                fontWeight: FontWeight.w400,
              ),
            ),
          ],
          if (required)
            const Text(
              ' *',
              style: TextStyle(
                color: Colors.red,
                fontSize: 14,
                fontWeight: FontWeight.w900,
              ),
            ),
        ],
      ),
    );
  }

  Widget _textField(
    String key,
    String hint,
    IconData icon, {
    bool required = false,
    bool numeric = false,
    ValueChanged<String>? onChanged,
  }) {
    return TextFormField(
      controller: ctrl(key),
      keyboardType: numeric
          ? const TextInputType.numberWithOptions(decimal: true)
          : TextInputType.text,
      onChanged: onChanged,
      validator: required
          ? (v) => (v ?? '').trim().isEmpty ? 'Champ obligatoire.' : null
          : null,
      style: const TextStyle(color: vendorText, fontSize: 14),
      decoration: _inputDecoration(hint, icon),
    );
  }

  Widget _counterField(
    String key,
    String hint,
    IconData icon,
    int max, {
    required bool required,
    int maxLines = 1,
  }) {
    return StatefulBuilder(
      builder: (context, localSet) {
        return Stack(
          children: [
            TextFormField(
              controller: ctrl(key),
              maxLines: maxLines,
              maxLength: max,
              onChanged: (_) => localSet(() {}),
              validator: required
                  ? (v) =>
                        (v ?? '').trim().isEmpty ? 'Champ obligatoire.' : null
                  : null,
              style: const TextStyle(color: vendorText, fontSize: 14),
              decoration: _inputDecoration(hint, icon).copyWith(
                counterText: '',
                contentPadding: EdgeInsets.fromLTRB(
                  48,
                  maxLines > 1 ? 14 : 15,
                  46,
                  maxLines > 1 ? 22 : 15,
                ),
              ),
            ),
            Positioned(
              right: 10,
              bottom: 7,
              child: Text(
                '${ctrl(key).text.length}/$max',
                style: const TextStyle(
                  color: Color(0xFF536C98),
                  fontSize: 10.5,
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _richDescription() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFC7D4E7)),
      ),
      child: Column(
        children: [
          SizedBox(
            height: 38,
            child: Row(
              children: [
                _toolbarButton(Icons.format_bold_rounded),
                _toolbarButton(Icons.format_italic_rounded),
                _toolbarButton(Icons.format_list_bulleted_rounded),
                _toolbarButton(Icons.format_list_numbered_rounded),
                const VerticalDivider(width: 10, indent: 7, endIndent: 7),
                _toolbarButton(Icons.format_indent_increase_rounded),
                _toolbarButton(Icons.link_rounded),
              ],
            ),
          ),
          const Divider(height: 1, color: Color(0xFFC7D4E7)),
          StatefulBuilder(
            builder: (context, localSet) {
              return Stack(
                children: [
                  TextFormField(
                    controller: ctrl('description'),
                    maxLength: 1000,
                    maxLines: 5,
                    onChanged: (_) => localSet(() {}),
                    validator: (v) => (v ?? '').trim().isEmpty
                        ? 'La description détaillée est obligatoire.'
                        : null,
                    decoration: const InputDecoration(
                      hintText:
                          'Décrivez votre produit en détail : caractéristiques, avantages, conseils d’utilisation...',
                      hintStyle: TextStyle(
                        color: Color(0xFF91A0BC),
                        fontSize: 12,
                      ),
                      border: InputBorder.none,
                      counterText: '',
                      contentPadding: EdgeInsets.fromLTRB(12, 12, 12, 24),
                    ),
                  ),
                  Positioned(
                    right: 10,
                    bottom: 6,
                    child: Text(
                      '${ctrl('description').text.length}/1000',
                      style: const TextStyle(
                        color: Color(0xFF536C98),
                        fontSize: 10.5,
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _toolbarButton(IconData icon) => IconButton(
    onPressed: () {},
    padding: EdgeInsets.zero,
    constraints: const BoxConstraints(minWidth: 34, minHeight: 34),
    icon: Icon(icon, color: vendorText, size: 19),
  );

  InputDecoration _inputDecoration(String hint, IconData icon) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Color(0xFF91A0BC), fontSize: 13.2),
      prefixIcon: Icon(icon, color: vendorText, size: 23),
      prefixIconConstraints: const BoxConstraints(minWidth: 48),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 15),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFC7D4E7)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFC7D4E7)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: vendorBlue, width: 1.3),
      ),
    );
  }

  Widget _moneyField(
    String key,
    String hint,
    IconData icon, {
    bool required = false,
  }) => _unitField(key, hint, icon, 'FCFA', required: required);

  Widget _unitField(
    String key,
    String hint,
    IconData icon,
    String unit, {
    bool required = false,
    ValueChanged<String>? onChanged,
  }) => TextFormField(
    controller: ctrl(key),
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    onChanged: onChanged,
    validator: required
        ? (v) => (v ?? '').trim().isEmpty ? 'Obligatoire.' : null
        : null,
    decoration: _inputDecoration(hint, icon).copyWith(
      suffixText: unit,
      suffixStyle: const TextStyle(color: Color(0xFF536C98), fontSize: 12),
      errorMaxLines: 2,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
    ),
  );

  Widget _selector<T>({
    required T? value,
    required Map<T, String> items,
    required String hint,
    required IconData icon,
    required ValueChanged<T> onChanged,
  }) {
    return ProductSheetSelector<T>(
      value: value,
      label: hint,
      items: items.keys.toList(),
      display: (v) => items[v] ?? '$v',
      hint: hint,
      icon: icon,
      onChanged: onChanged,
    );
  }

  Widget _categorySelector({
    required String label,
    required int? value,
    required List<Map<String, dynamic>> items,
    required String hint,
    required bool required,
    required ValueChanged<int> onChanged,
    bool enabled = true,
  }) {
    final ids = items
        .map((item) => int.tryParse('${item['id']}'))
        .whereType<int>()
        .toList();
    String display(int id) {
      for (final item in items) {
        if (int.tryParse('${item['id']}') == id)
          return '${item['name'] ?? 'Catégorie'}';
      }
      return 'Catégorie';
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _fieldTitle(label, required: required),
        Opacity(
          opacity: enabled ? 1 : .55,
          child: IgnorePointer(
            ignoring: !enabled,
            child: ProductSheetSelector<int>(
              value: value,
              label: label,
              items: ids,
              display: display,
              hint: hint,
              icon: Icons.grid_view_rounded,
              onChanged: onChanged,
            ),
          ),
        ),
      ],
    );
  }

  Widget _yesNo(
    String label,
    bool value,
    IconData yesIcon,
    ValueChanged<bool> onChanged,
  ) {
    Widget choice(bool yes) {
      final selected = value == yes;
      return Expanded(
        child: InkWell(
          onTap: () => onChanged(yes),
          borderRadius: BorderRadius.circular(8),
          child: Container(
            height: 64,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFC7D4E7)),
            ),
            child: Row(
              children: [
                Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: selected ? productOrange : const Color(0xFFA7B3C6),
                      width: 2,
                    ),
                  ),
                  child: selected
                      ? Center(
                          child: Container(
                            width: 13,
                            height: 13,
                            decoration: const BoxDecoration(
                              color: productOrange,
                              shape: BoxShape.circle,
                            ),
                          ),
                        )
                      : null,
                ),
                const SizedBox(width: 12),
                if (yes) ...[
                  Icon(yesIcon, color: vendorText, size: 21),
                  const SizedBox(width: 10),
                ],
                Text(
                  yes ? 'Oui' : 'Non',
                  style: const TextStyle(
                    color: vendorText,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _fieldTitle(label, required: true),
        Row(children: [choice(true), const SizedBox(width: 12), choice(false)]),
      ],
    );
  }

  Widget _uploadBox({
    required IconData icon,
    IconData? leadingIcon,
    required String title,
    required String subtitle,
    required String details,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: CustomPaint(
        painter: _DashedBorderPainter(
          color: const Color(0xFF82B6FF),
          radius: 8,
        ),
        child: Container(
          width: double.infinity,
          constraints: const BoxConstraints(minHeight: 96),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
          child: Row(
            children: [
              if (leadingIcon != null) ...[
                Icon(leadingIcon, color: vendorText, size: 27),
                const SizedBox(width: 18),
              ],
              Icon(
                icon,
                color: icon == Icons.ondemand_video_outlined
                    ? const Color(0xFF4E6497)
                    : const Color(0xFF0875E1),
                size: 52,
              ),
              const SizedBox(width: 20),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: vendorText,
                        fontSize: 14.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        color: Color(0xFF536C98),
                        fontSize: 10.7,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      details,
                      style: const TextStyle(
                        color: Color(0xFF536C98),
                        fontSize: 10.2,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _imageSlots() {
    return SizedBox(
      height: 100,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: (_images.length + 1).clamp(5, 10),
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          if (index < _images.length) {
            return Stack(
              children: [
                Container(
                  width: 92,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(7),
                    border: Border.all(
                      color: index == 0
                          ? const Color(0xFF0875E1)
                          : const Color(0xFFC7D4E7),
                      width: index == 0 ? 1.5 : 1,
                    ),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(6),
                    child: Image.file(
                      _images[index],
                      width: 92,
                      height: 100,
                      fit: BoxFit.cover,
                    ),
                  ),
                ),
                if (index == 0)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 5),
                      decoration: const BoxDecoration(
                        color: Color(0xFF0875E1),
                        borderRadius: BorderRadius.vertical(
                          bottom: Radius.circular(6),
                        ),
                      ),
                      child: const Text(
                        'Image principale',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.white, fontSize: 9),
                      ),
                    ),
                  ),
                Positioned(
                  top: 3,
                  right: 3,
                  child: InkWell(
                    onTap: () => setState(() => _images.removeAt(index)),
                    child: const CircleAvatar(
                      radius: 10,
                      backgroundColor: Colors.white,
                      child: Icon(
                        Icons.close_rounded,
                        color: Colors.red,
                        size: 13,
                      ),
                    ),
                  ),
                ),
              ],
            );
          }
          return InkWell(
            onTap: _images.length >= 10 ? null : _pickImages,
            child: CustomPaint(
              painter: _DashedBorderPainter(
                color: const Color(0xFFB7CBE7),
                radius: 7,
              ),
              child: const SizedBox(
                width: 92,
                height: 100,
                child: Center(
                  child: Icon(
                    Icons.add_rounded,
                    color: Color(0xFF4E6497),
                    size: 30,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _wizardButtons({String firstLabel = 'Précédent'}) {
    return Row(
      children: [
        Expanded(
          flex: 5,
          child: SizedBox(
            height: 58,
            child: OutlinedButton.icon(
              onPressed: _previous,
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: Color(0xFFB8C6DC)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              icon: const Icon(
                Icons.arrow_back_rounded,
                color: vendorText,
                size: 28,
              ),
              label: FittedBox(
                child: Text(
                  firstLabel,
                  style: const TextStyle(
                    color: vendorText,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(width: 24),
        Expanded(
          flex: 7,
          child: SizedBox(
            height: 58,
            child: FilledButton(
              onPressed: _next,
              style: FilledButton.styleFrom(
                backgroundColor: productOrange,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Suivant',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  SizedBox(width: 15),
                  Icon(
                    Icons.arrow_forward_rounded,
                    color: Colors.white,
                    size: 28,
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _pair(Widget left, Widget right) => _fieldColumns([left, right]);

  Widget _fieldColumns(List<Widget> fields) => LayoutBuilder(
    builder: (context, constraints) {
      final minimum = MediaQuery.textScalerOf(context).scale(240);
      if (constraints.maxWidth <
          fields.length * minimum + (fields.length - 1) * 16) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (var i = 0; i < fields.length; i++) ...[
              fields[i],
              if (i < fields.length - 1) const SizedBox(height: 16),
            ],
          ],
        );
      }
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (var i = 0; i < fields.length; i++) ...[
            Expanded(child: fields[i]),
            if (i < fields.length - 1) const SizedBox(width: 16),
          ],
        ],
      );
    },
  );
}

class _WizardHeader extends StatelessWidget {
  const _WizardHeader({
    required this.title,
    required this.subtitle,
    required this.onBack,
  });
  final String title;
  final String subtitle;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF052B61), Color(0xFF0B4F92)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 8, 18, 10),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 88),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                InkWell(
                  onTap: onBack,
                  borderRadius: BorderRadius.circular(40),
                  child: Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: .08),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: Colors.white.withValues(alpha: .16),
                      ),
                    ),
                    child: const Icon(
                      Icons.arrow_back_rounded,
                      color: Colors.white,
                      size: 28,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                const Expanded(flex: 6, child: ProductBrandLockup()),
                const SizedBox(width: 8),
                Expanded(
                  flex: 5,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.right,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 17,
                          height: 1.05,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        subtitle,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.right,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10.8,
                          height: 1.18,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StepProgress extends StatelessWidget {
  const _StepProgress({required this.currentStep});
  final int currentStep;

  @override
  Widget build(BuildContext context) {
    const labels = [
      'Informations',
      'Prix & unité',
      'Détail technique',
      'Logistique',
      'Médias',
    ];
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: List.generate(labels.length, (index) {
        final current = index == currentStep;
        final completed = index < currentStep;
        final color = current
            ? productOrange
            : completed
            ? const Color(0xFF087AF0)
            : const Color(0xFFC5CDD9);
        return Expanded(
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              if (index > 0)
                Positioned(
                  left: -MediaQuery.sizeOf(context).width / 12,
                  top: 14,
                  child: Container(
                    width: MediaQuery.sizeOf(context).width / 6,
                    height: 2,
                    color: completed || current
                        ? const Color(0xFF1680F0)
                        : const Color(0xFFC5CDD9),
                  ),
                ),
              Column(
                children: [
                  Container(
                    width: 30,
                    height: 30,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: color,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      '${index + 1}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2),
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        labels[index],
                        style: TextStyle(
                          color: current
                              ? productOrange
                              : completed
                              ? const Color(0xFF087AF0)
                              : const Color(0xFF536C98),
                          fontSize: 10,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      }),
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.child,
  });
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 15, 14, 18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE1E8F1)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0A2A63),
            blurRadius: 18,
            offset: Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF0E7),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, color: productOrange, size: 38),
              ),
              const SizedBox(width: 15),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        color: vendorText,
                        fontSize: 27,
                        height: 1.05,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        color: Color(0xFF536C98),
                        fontSize: 15,
                        height: 1.25,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          child,
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
      ..strokeWidth = 1.2;
    final path = Path()
      ..addRRect(
        RRect.fromRectAndRadius(Offset.zero & size, Radius.circular(radius)),
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
    return const Center(child: CircularProgressIndicator(color: productOrange));
  }
}
