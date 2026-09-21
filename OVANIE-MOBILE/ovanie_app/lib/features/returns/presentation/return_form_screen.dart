import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../cart/presentation/cart_screen.dart';
import '../../catalog/presentation/catalog_screen.dart';
import '../../favorites/presentation/favorites_screen.dart';
import '../data/returns_repository.dart';
import '../domain/return_model.dart';

/// Parcours "Préparer le retour" en 4 étapes, reconstruit pour l'application
/// mobile. Il fonctionne aussi bien pour une nouvelle demande que pour la
/// préparation d'un dossier déjà approuvé.
class ReturnFormScreen extends StatefulWidget {
  final int? initialOrderId;
  final int? initialOrderItemId;
  final ClientReturnCase? existingCase;

  const ReturnFormScreen({
    super.key,
    this.initialOrderId,
    this.initialOrderItemId,
    this.existingCase,
  });

  @override
  State<ReturnFormScreen> createState() => _ReturnFormScreenState();
}

class _ReturnFormScreenState extends State<ReturnFormScreen> {
  final ReturnsRepository _repository = const ReturnsRepository();
  final ImagePicker _picker = ImagePicker();

  final TextEditingController _problemController = TextEditingController();
  final TextEditingController _detailsController = TextEditingController();
  final TextEditingController _storageController = TextEditingController();
  final TextEditingController _pickupAddressController = TextEditingController();
  final TextEditingController _contactNameController = TextEditingController();
  final TextEditingController _contactPhoneController = TextEditingController();
  final TextEditingController _contactEmailController = TextEditingController();

  ReturnCenterData? _center;
  EligibleReturnOrder? _order;
  EligibleReturnItem? _item;
  ClientReturnCase? _existing;

  bool _loading = true;
  bool _submitting = false;
  String? _error;
  int _step = 1;
  int _quantity = 1;
  String _reason = 'Produit endommagé';
  DateTime _discoveryDate = DateTime.now();
  DateTime _pickupDate = DateTime.now().add(const Duration(days: 2));
  String _pickupSlot = 'Matin (8h – 12h)';
  List<XFile> _photos = <XFile>[];
  List<XFile> _videos = <XFile>[];

  static const List<String> _reasons = <String>[
    'Produit endommagé',
    'Produit non conforme',
    'Erreur de livraison',
    'Mauvais article reçu',
    'Article incomplet',
    'Problème de qualité',
    'Autre motif',
  ];

  static const List<String> _timeSlots = <String>[
    'Matin (8h – 12h)',
    'Après-midi (12h – 16h)',
    'Fin de journée (16h – 19h)',
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _problemController.dispose();
    _detailsController.dispose();
    _storageController.dispose();
    _pickupAddressController.dispose();
    _contactNameController.dispose();
    _contactPhoneController.dispose();
    _contactEmailController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      if (widget.existingCase != null) {
        final full = await _repository.fetchCase(widget.existingCase!.id);
        if (!mounted) return;
        _existing = full;
        _quantity = full.quantity > 0 ? full.quantity : 1;
        _reason = full.reason.trim().isEmpty ? _reasons.first : full.reason.trim();
        if (!_reasons.contains(_reason)) _reason = _reasons.first;
        _problemController.text = full.detailedDescription;
        _detailsController.text = full.detailedDescription;
        _discoveryDate = full.discoveryDate ?? full.requestDate ?? DateTime.now();
        _pickupDate = full.pickupDate ?? DateTime.now().add(const Duration(days: 2));
        _pickupSlot = full.pickupTimeSlot.trim().isEmpty ? _timeSlots.first : full.pickupTimeSlot;
        if (!_timeSlots.contains(_pickupSlot)) _pickupSlot = _timeSlots.first;
        _storageController.text = full.storageLocation.trim().isNotEmpty
            ? full.storageLocation
            : (full.collectionAddress.isNotEmpty ? full.collectionAddress : 'Adresse de livraison');
        _pickupAddressController.text = full.collectionAddress;
        _contactNameController.text = full.pickupContactName.trim().isNotEmpty
            ? full.pickupContactName
            : full.recipientName;
        _contactPhoneController.text = full.pickupContactPhone.trim().isNotEmpty
            ? full.pickupContactPhone
            : full.recipientPhone;
        _contactEmailController.text = full.pickupContactEmail.trim().isNotEmpty
            ? full.pickupContactEmail
            : full.recipientEmail;
        setState(() => _loading = false);
        return;
      }

      final center = await _repository.fetchCenter();
      if (!mounted) return;
      EligibleReturnOrder? order;
      if (widget.initialOrderId != null) {
        for (final candidate in center.eligibleOrders) {
          if (candidate.id == widget.initialOrderId) {
            order = candidate;
            break;
          }
        }
      }
      order ??= center.eligibleOrders.isNotEmpty ? center.eligibleOrders.first : null;

      EligibleReturnItem? item;
      if (order != null && widget.initialOrderItemId != null) {
        for (final candidate in order.items) {
          if (candidate.id == widget.initialOrderItemId) {
            item = candidate;
            break;
          }
        }
      }
      item ??= order != null && order.items.isNotEmpty ? order.items.first : null;

      _center = center;
      _order = order;
      _item = item;
      _quantity = 1;
      _pickupAddressController.text = order?.collectionAddress ?? '';
      _storageController.text = order?.collectionAddress ?? '';
      _contactNameController.text = order?.recipientName ?? '';
      _contactPhoneController.text = order?.recipientPhone ?? '';
      _contactEmailController.text = order?.recipientEmail ?? '';
      setState(() => _loading = false);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  int get _maxQuantity {
    if (_existing != null) return _existing!.quantity.clamp(1, 9999).toInt();
    final max = _item?.availableReturnQuantity ?? 1;
    return max > 0 ? max : 1;
  }

  int? get _orderId => _existing?.orderId ?? _order?.id;
  int? get _orderItemId => _existing?.orderItemId ?? _item?.id;
  String get _orderNumber => _existing?.orderNumber ?? _order?.orderNumber ?? '';
  String get _productName => _existing?.productName ?? _item?.productName ?? 'Produit OVANIE';
  String get _imageUrl => _existing?.imageUrl ?? _item?.imageUrl ?? '';
  double? get _unitPrice => _existing?.unitPrice ?? _item?.unitPrice;
  DateTime? get _purchaseDate => _existing?.orderCreatedAt ?? _order?.createdAt;

  Future<void> _pickPhotos() async {
    final picked = await _picker.pickMultiImage(imageQuality: 84);
    if (!mounted || picked.isEmpty) return;
    final merged = <XFile>[..._photos, ...picked];
    final unique = <String, XFile>{for (final file in merged) file.path: file};
    setState(() => _photos = unique.values.take(5).toList(growable: false));
  }

  Future<void> _pickVideo() async {
    final picked = await _picker.pickVideo(
      source: ImageSource.gallery,
      maxDuration: const Duration(minutes: 1),
    );
    if (!mounted || picked == null) return;
    setState(() => _videos = <XFile>[picked]);
  }

  void _removePhoto(int index) {
    setState(() => _photos.removeAt(index));
  }

  String? _validateStep() {
    if (_step == 1) {
      if (_problemController.text.trim().length < 10) {
        return 'Décrivez le problème rencontré en au moins 10 caractères.';
      }
      if (_photos.isEmpty && (_existing?.photoCount ?? 0) == 0) {
        return 'Ajoutez au moins une photo claire du produit.';
      }
    }
    if (_step == 2) {
      if (_detailsController.text.trim().length < 10) {
        return 'Ajoutez quelques détails supplémentaires sur le problème.';
      }
      if (_storageController.text.trim().isEmpty) {
        return 'Indiquez le lieu où le produit est actuellement stocké.';
      }
    }
    if (_step == 3) {
      if (_pickupAddressController.text.trim().isEmpty) {
        return 'Renseignez l’adresse de collecte.';
      }
      if (_contactNameController.text.trim().isEmpty || _contactPhoneController.text.trim().isEmpty) {
        return 'Renseignez le nom et le téléphone du contact pour la collecte.';
      }
    }
    return null;
  }

  void _continue() {
    final message = _validateStep();
    if (message != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      return;
    }
    if (_step < 4) {
      setState(() => _step += 1);
    }
  }

  void _backStep() {
    if (_step > 1) {
      setState(() => _step -= 1);
    } else {
      Navigator.of(context).maybePop();
    }
  }

  Future<void> _submit() async {
    final orderId = _orderId;
    final orderItemId = _orderItemId;
    if (orderId == null || orderItemId == null) {
      setState(() => _error = 'Le produit ou la commande liés au retour sont indisponibles.');
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final problem = _problemController.text.trim();
      final additional = _detailsController.text.trim();
      final description = additional.isEmpty || additional == problem
          ? problem
          : '$problem\n$additional';
      final result = await _repository.submit(
        existingReturnId: _existing?.id,
        orderId: orderId,
        orderItemId: orderItemId,
        type: _existing?.type == 'refund' ? 'refund' : 'return',
        quantity: _quantity,
        reason: _reason,
        detailedDescription: description,
        discoveryDate: _discoveryDate,
        storageLocation: _storageController.text.trim(),
        pickupAddress: _pickupAddressController.text.trim(),
        pickupContactName: _contactNameController.text.trim(),
        pickupContactPhone: _contactPhoneController.text.trim(),
        pickupContactEmail: _contactEmailController.text.trim(),
        pickupDate: _pickupDate,
        pickupTimeSlot: _pickupSlot,
        photoPaths: _photos.map((file) => file.path).toList(growable: false),
        videoPaths: _videos.map((file) => file.path).toList(growable: false),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Votre retour a bien été enregistré.')),
      );
      Navigator.of(context).pop(result);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _pickDate({required bool pickup}) async {
    final initial = pickup ? _pickupDate : _discoveryDate;
    final selected = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: pickup ? DateTime.now() : DateTime(2020, 1, 1),
      lastDate: pickup ? DateTime.now().add(const Duration(days: 45)) : DateTime.now(),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (pickup) {
        _pickupDate = selected;
      } else {
        _discoveryDate = selected;
      }
    });
  }

  Future<void> _editCollectionAddress() async {
    final controller = TextEditingController(text: _pickupAddressController.text);
    final value = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Adresse de collecte'),
        content: TextField(
          controller: controller,
          minLines: 2,
          maxLines: 3,
          decoration: const InputDecoration(hintText: 'Adresse complète de collecte'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, controller.text.trim()), child: const Text('Enregistrer')),
        ],
      ),
    );
    controller.dispose();
    if (value != null && value.isNotEmpty && mounted) {
      setState(() => _pickupAddressController.text = value);
    }
  }

  Future<void> _editContact() async {
    final name = TextEditingController(text: _contactNameController.text);
    final phone = TextEditingController(text: _contactPhoneController.text);
    final email = TextEditingController(text: _contactEmailController.text);
    final result = await showDialog<List<String>>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Contact pour la collecte'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: name, decoration: const InputDecoration(labelText: 'Nom complet')),
            const SizedBox(height: 10),
            TextField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Téléphone')),
            const SizedBox(height: 10),
            TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'E-mail')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Annuler')),
          FilledButton(
            onPressed: () => Navigator.pop(context, <String>[name.text.trim(), phone.text.trim(), email.text.trim()]),
            child: const Text('Enregistrer'),
          ),
        ],
      ),
    );
    name.dispose();
    phone.dispose();
    email.dispose();
    if (result != null && mounted) {
      setState(() {
        _contactNameController.text = result[0];
        _contactPhoneController.text = result[1];
        _contactEmailController.text = result[2];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: const OvanieBottomNavigation(
        selectedTab: OvanieMainTab.account,
      ),
      body: SafeArea(
        bottom: false,
        child: _loading
            ? const Center(child: CircularProgressIndicator(color: Color(0xFF0B45F5)))
            : _error != null && _orderId == null
                ? _ErrorView(message: _error!, onRetry: _load)
                : _buildContent(),
      ),
    );
  }

  Widget _buildContent() {
    if (_existing == null && (_center?.eligibleOrders.isEmpty ?? true)) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: Text(
            'Aucun article n’est actuellement éligible à un retour.',
            textAlign: TextAlign.center,
            style: TextStyle(color: OvanieColors.muted, fontSize: 15, height: 1.45),
          ),
        ),
      );
    }

    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      slivers: [
        SliverToBoxAdapter(child: _ReturnHeader(step: _step, onBack: _backStep)),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 26),
          sliver: SliverList(
            delegate: SliverChildListDelegate.fixed([
              _StepProgress(step: _step),
              const SizedBox(height: 18),
              _StepInfo(step: _step),
              const SizedBox(height: 16),
              if (_step == 1) _buildStepOne(),
              if (_step == 2) _buildStepTwo(),
              if (_step == 3) _buildStepThree(),
              if (_step == 4) _buildStepFour(),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: OvanieColors.danger, fontSize: 12.5)),
              ],
              const SizedBox(height: 18),
              _ActionBar(
                step: _step,
                submitting: _submitting,
                onBack: _backStep,
                onContinue: _step == 4 ? _submit : _continue,
              ),
              const SizedBox(height: 10),
            ]),
          ),
        ),
      ],
    );
  }

  Widget _buildStepOne() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ProductCard(
          name: _productName,
          orderNumber: _orderNumber,
          purchasedAt: _purchaseDate,
          imageUrl: _imageUrl,
          price: _unitPrice,
          quantity: _existing?.quantity ?? _item?.orderedQuantity ?? _quantity,
        ),
        const SizedBox(height: 20),
        _FieldLabel(title: 'Motif du retour', subtitle: 'Sélectionnez la raison principale de votre retour', requiredField: true),
        const SizedBox(height: 8),
        _SelectBox(
          value: _reason,
          items: _reasons,
          onChanged: (value) => setState(() => _reason = value),
        ),
        const SizedBox(height: 18),
        const _FieldLabel(
          title: 'Description détaillée',
          subtitle: 'Expliquez le problème rencontré en quelques mots',
          requiredField: true,
        ),
        const SizedBox(height: 8),
        _LargeTextField(controller: _problemController, hint: 'Décrivez précisément le problème rencontré.'),
        const SizedBox(height: 18),
        const _FieldLabel(
          title: 'Quantité à retourner',
          subtitle: 'Sélectionnez la quantité que vous souhaitez retourner',
          requiredField: true,
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            _QuantitySelector(
              value: _quantity,
              max: _maxQuantity,
              onChanged: (value) => setState(() => _quantity = value),
            ),
            const SizedBox(width: 14),
            Text('sur $_maxQuantity acheté(s)', style: const TextStyle(color: Color(0xFF46597C), fontSize: 12.5)),
          ],
        ),
        const SizedBox(height: 20),
        const _FieldLabel(
          title: 'Photos du produit (obligatoire)',
          subtitle: 'Ajoutez des photos claires du problème (max. 5 photos)',
          requiredField: true,
        ),
        const SizedBox(height: 10),
        _MediaRow(
          photos: _photos,
          onAddPhoto: _pickPhotos,
          onRemovePhoto: _removePhoto,
          allowVideo: false,
          hasExistingMedia: (_existing?.photoCount ?? 0) > 0,
        ),
        const SizedBox(height: 16),
        const _TipCard(
          icon: Icons.info_rounded,
          title: 'Bon à savoir',
          text: 'Les photos aident notre équipe à traiter votre demande plus rapidement.',
        ),
      ],
    );
  }

  Widget _buildStepTwo() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ProductCard(
          name: _productName,
          orderNumber: _orderNumber,
          purchasedAt: _purchaseDate,
          imageUrl: _imageUrl,
          price: _unitPrice,
          quantity: _quantity,
        ),
        const SizedBox(height: 20),
        const _FieldLabel(
          title: 'Détails supplémentaires',
          subtitle: 'Décrivez plus précisément le problème rencontré',
          requiredField: true,
        ),
        const SizedBox(height: 8),
        _LargeTextField(controller: _detailsController, hint: 'Ajoutez les détails utiles pour comprendre le problème.'),
        const SizedBox(height: 18),
        const _FieldLabel(
          title: 'Date de découverte du problème',
          subtitle: 'Indiquez quand vous avez constaté le problème',
          requiredField: true,
        ),
        const SizedBox(height: 8),
        _DateBox(date: _discoveryDate, onTap: () => _pickDate(pickup: false)),
        const SizedBox(height: 18),
        const _FieldLabel(
          title: 'Lieu de stockage du produit au moment du problème',
          subtitle: 'Où le produit était-il stocké lorsque vous avez constaté le problème ?',
          requiredField: true,
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _storageController,
          decoration: const InputDecoration(
            prefixIcon: Icon(Icons.home_outlined, color: OvanieColors.navy),
            hintText: 'Ex. Chantier – commune, quartier',
          ),
        ),
        const SizedBox(height: 20),
        const _FieldLabel(
          title: 'Photos / vidéos du problème (obligatoire)',
          subtitle: 'Ajoutez des photos ou vidéos claires (max. 5 fichiers)',
          requiredField: true,
        ),
        const SizedBox(height: 10),
        _MediaRow(
          photos: _photos,
          onAddPhoto: _pickPhotos,
          onRemovePhoto: _removePhoto,
          allowVideo: true,
          videoCount: _videos.length,
          onAddVideo: _pickVideo,
          hasExistingMedia: ((_existing?.photoCount ?? 0) + (_existing?.videoCount ?? 0)) > 0,
        ),
        const SizedBox(height: 16),
        const _TipCard(
          icon: Icons.lightbulb_outline_rounded,
          title: 'Conseil',
          text: 'Prenez des photos nettes sous plusieurs angles pour nous aider à bien comprendre le problème.',
        ),
      ],
    );
  }

  Widget _buildStepThree() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _ProductCard(
          name: _productName,
          orderNumber: _orderNumber,
          purchasedAt: _purchaseDate,
          imageUrl: _imageUrl,
          price: _unitPrice,
          quantity: _quantity,
        ),
        const SizedBox(height: 18),
        _CollectionCard(
          icon: Icons.location_on_outlined,
          title: 'Adresse de collecte',
          requiredField: true,
          main: _pickupAddressController.text.isEmpty ? 'Adresse à renseigner' : _pickupAddressController.text,
          secondary: 'Adresse utilisée pour la récupération du produit.',
          onEdit: _editCollectionAddress,
        ),
        const SizedBox(height: 14),
        _CollectionCard(
          icon: Icons.person_outline_rounded,
          title: 'Contact pour la collecte',
          requiredField: true,
          main: _contactNameController.text.isEmpty ? 'Contact à renseigner' : _contactNameController.text,
          secondary: [
            _contactPhoneController.text,
            _contactEmailController.text,
          ].where((value) => value.trim().isNotEmpty).join('\n'),
          footer: 'Notre équipe vous contactera pour organiser la récupération.',
          onEdit: _editContact,
        ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
          decoration: _cardDecoration(),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const _FieldLabel(title: 'Date souhaitée de collecte', requiredField: true),
                    const SizedBox(height: 8),
                    _DateBox(date: _pickupDate, onTap: () => _pickDate(pickup: true)),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const _FieldLabel(title: 'Créneau horaire préféré', requiredField: true),
                    const SizedBox(height: 8),
                    _SelectBox(value: _pickupSlot, items: _timeSlots, onChanged: (value) => setState(() => _pickupSlot = value)),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const _TipCard(
          icon: Icons.lightbulb_outline_rounded,
          title: 'Conseil',
          text: 'Emballez le produit aussi bien que possible et gardez-le accessible pour faciliter la collecte.',
        ),
      ],
    );
  }

  Widget _buildStepFour() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Récapitulatif du retour', style: TextStyle(color: OvanieColors.navy, fontSize: 18, fontWeight: FontWeight.w900)),
        const SizedBox(height: 12),
        Container(
          decoration: _cardDecoration(),
          child: Column(
            children: [
              InkWell(
                onTap: () => setState(() => _step = 1),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: _ProductSummary(
                    name: _productName,
                    orderNumber: _orderNumber,
                    purchasedAt: _purchaseDate,
                    imageUrl: _imageUrl,
                    price: _unitPrice,
                    quantity: _quantity,
                  ),
                ),
              ),
              const Divider(height: 1, color: OvanieColors.border),
              _SummaryRow(icon: Icons.inventory_2_outlined, tint: const Color(0xFFFFEEE5), color: OvanieColors.orange, title: 'Motif du retour', value: _reason, onTap: () => setState(() => _step = 1)),
              const Divider(height: 1, color: OvanieColors.border),
              _SummaryRow(icon: Icons.description_outlined, tint: const Color(0xFFEAF3FF), color: const Color(0xFF1262D8), title: 'Description du problème', value: _problemController.text.trim(), onTap: () => setState(() => _step = 1)),
              const Divider(height: 1, color: OvanieColors.border),
              _SummaryRow(icon: Icons.calendar_today_outlined, tint: const Color(0xFFEAF8EF), color: const Color(0xFF1C9C55), title: 'Date de découverte du problème', value: _dateLabel(_discoveryDate), onTap: () => setState(() => _step = 2)),
              const Divider(height: 1, color: OvanieColors.border),
              _SummaryRow(
                icon: Icons.local_shipping_outlined,
                tint: const Color(0xFFF4EAFE),
                color: const Color(0xFF8E35E8),
                title: 'Mode de retour',
                value: 'Collecte à l’adresse indiquée par OVANIE',
                onTap: () => setState(() => _step = 3),
              ),
              const Divider(height: 1, color: OvanieColors.border),
              _SummaryConditions(onTap: () => setState(() => _step = 3)),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const _ConfirmNotice(),
      ],
    );
  }
}

class _ReturnHeader extends StatelessWidget {
  final int step;
  final VoidCallback onBack;
  const _ReturnHeader({required this.step, required this.onBack});

  @override
  Widget build(BuildContext context) {
    final label = switch (step) {
      1 => 'Informations sur le retour',
      2 => 'Détails & preuves',
      3 => 'Livraison du retour',
      _ => 'Vérification & confirmation',
    };
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 8, 18, 18),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconButton(
            onPressed: onBack,
            icon: const Icon(Icons.arrow_back_rounded, color: OvanieColors.navy, size: 29),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(top: 7),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Préparer le retour', style: TextStyle(color: OvanieColors.navy, fontSize: 24, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 5),
                  Text('Étape $step sur 4 • $label', style: const TextStyle(color: Color(0xFF31456D), fontSize: 13.5, fontWeight: FontWeight.w500)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StepProgress extends StatelessWidget {
  final int step;
  const _StepProgress({required this.step});

  @override
  Widget build(BuildContext context) {
    const labels = <String>['Informations\nsur le retour', 'Détails & preuves', 'Livraison du retour', 'Vérification\n& confirmation'];
    return Column(
      children: [
        SizedBox(
          height: 34,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final segment = constraints.maxWidth / 4;
              return Stack(
                children: [
                  Positioned(left: segment / 2, right: segment / 2, top: 15, child: Container(height: 1.5, color: const Color(0xFFD8E0ED))),
                  Positioned(
                    left: segment / 2,
                    top: 15,
                    child: Container(
                      height: 1.5,
                      width: (segment * (step - 1)).clamp(0.0, segment * 3).toDouble(),
                      color: OvanieColors.navy,
                    ),
                  ),
                  Row(
                    children: List.generate(4, (index) {
                      final number = index + 1;
                      final done = number < step;
                      final active = number == step;
                      return Expanded(
                        child: Center(
                          child: Container(
                            width: 30,
                            height: 30,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: done ? OvanieColors.navy : active ? const Color(0xFF0B45F5) : Colors.white,
                              border: Border.all(color: done || active ? (done ? OvanieColors.navy : const Color(0xFF0B45F5)) : const Color(0xFFCAD4E4), width: 1.4),
                            ),
                            child: done
                                ? const Icon(Icons.check_rounded, size: 18, color: Colors.white)
                                : Text('$number', style: TextStyle(color: active ? Colors.white : OvanieColors.navy, fontSize: 12, fontWeight: FontWeight.w800)),
                          ),
                        ),
                      );
                    }),
                  ),
                ],
              );
            },
          ),
        ),
        const SizedBox(height: 4),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: List.generate(4, (index) {
            final active = index + 1 == step;
            final done = index + 1 < step;
            return Expanded(
              child: Text(
                labels[index],
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: active ? const Color(0xFF0B45F5) : done ? OvanieColors.navy : const Color(0xFF40547A),
                  fontSize: 10.7,
                  height: 1.28,
                  fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                ),
              ),
            );
          }),
        ),
      ],
    );
  }
}

class _StepInfo extends StatelessWidget {
  final int step;
  const _StepInfo({required this.step});

  @override
  Widget build(BuildContext context) {
    final text = switch (step) {
      1 => 'Remplissez les informations ci-dessous pour initier votre demande de retour. Notre équipe vous guidera ensuite pour la suite du processus.',
      2 => 'Ajoutez autant de détails et de preuves que possible afin d’accélérer le traitement de votre demande.',
      3 => 'OVANIE organisera la récupération de votre retour directement à l’adresse renseignée ci-dessous.',
      _ => 'Vérifiez les informations ci-dessous avant de confirmer votre demande de retour.',
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(
        color: const Color(0xFFF6FAFF),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: const Color(0xFFCFE2FF)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline_rounded, color: Color(0xFF0A66E8), size: 25),
          const SizedBox(width: 12),
          Expanded(child: Text(text, style: const TextStyle(color: OvanieColors.navy, fontSize: 12.3, height: 1.45, fontWeight: FontWeight.w500))),
        ],
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  final String name;
  final String orderNumber;
  final DateTime? purchasedAt;
  final String imageUrl;
  final double? price;
  final int quantity;
  const _ProductCard({required this.name, required this.orderNumber, required this.purchasedAt, required this.imageUrl, required this.price, required this.quantity});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Produit concerné', style: TextStyle(color: OvanieColors.navy, fontSize: 16, fontWeight: FontWeight.w900)),
          const SizedBox(height: 16),
          _ProductSummary(name: name, orderNumber: orderNumber, purchasedAt: purchasedAt, imageUrl: imageUrl, price: price, quantity: quantity),
        ],
      ),
    );
  }
}

class _ProductSummary extends StatelessWidget {
  final String name;
  final String orderNumber;
  final DateTime? purchasedAt;
  final String imageUrl;
  final double? price;
  final int quantity;
  const _ProductSummary({required this.name, required this.orderNumber, required this.purchasedAt, required this.imageUrl, required this.price, required this.quantity});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        _NetworkProductImage(url: imageUrl, size: 94),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 16, height: 1.15, fontWeight: FontWeight.w900)),
              const SizedBox(height: 6),
              Text('Commande #$orderNumber', style: const TextStyle(color: Color(0xFF40547A), fontSize: 12)),
              if (purchasedAt != null) ...[
                const SizedBox(height: 5),
                Text('Acheté le ${_dateLabel(purchasedAt!)}', style: const TextStyle(color: Color(0xFF40547A), fontSize: 12)),
              ],
              if (price != null) ...[
                const SizedBox(height: 8),
                Text(formatFcfa(price!), style: const TextStyle(color: OvanieColors.orange, fontSize: 16, fontWeight: FontWeight.w900)),
              ],
              const SizedBox(height: 5),
              Text('Qté : $quantity', style: const TextStyle(color: Color(0xFF40547A), fontSize: 12)),
            ],
          ),
        ),
        const Icon(Icons.chevron_right_rounded, color: OvanieColors.navy),
      ],
    );
  }
}

class _NetworkProductImage extends StatelessWidget {
  final String url;
  final double size;
  const _NetworkProductImage({required this.url, this.size = 86});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: url.isEmpty
          ? const Center(child: Icon(Icons.inventory_2_outlined, color: Color(0xFFB6C0D0), size: 45))
          : Image.network(
              url,
              fit: BoxFit.contain,
              errorBuilder: (_, __, ___) => const Center(child: Icon(Icons.inventory_2_outlined, color: Color(0xFFB6C0D0), size: 45)),
            ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  final String title;
  final String? subtitle;
  final bool requiredField;
  const _FieldLabel({required this.title, this.subtitle, this.requiredField = false});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        RichText(
          text: TextSpan(
            style: const TextStyle(color: OvanieColors.navy, fontSize: 14.2, fontWeight: FontWeight.w900),
            children: [
              TextSpan(text: title),
              if (requiredField) const TextSpan(text: ' *', style: TextStyle(color: OvanieColors.orange)),
            ],
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 4),
          Text(subtitle!, style: const TextStyle(color: Color(0xFF40547A), fontSize: 11.5, height: 1.3)),
        ],
      ],
    );
  }
}

class _SelectBox extends StatelessWidget {
  final String value;
  final List<String> items;
  final ValueChanged<String> onChanged;
  const _SelectBox({required this.value, required this.items, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      value: items.contains(value) ? value : items.first,
      isExpanded: true,
      decoration: const InputDecoration(contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 12)),
      icon: const Icon(Icons.keyboard_arrow_down_rounded, color: OvanieColors.navy),
      items: items.map((item) => DropdownMenuItem<String>(value: item, child: Text(item, overflow: TextOverflow.ellipsis))).toList(growable: false),
      onChanged: (value) {
        if (value != null) onChanged(value);
      },
    );
  }
}

class _LargeTextField extends StatefulWidget {
  final TextEditingController controller;
  final String hint;
  const _LargeTextField({required this.controller, required this.hint});

  @override
  State<_LargeTextField> createState() => _LargeTextFieldState();
}

class _LargeTextFieldState extends State<_LargeTextField> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    super.dispose();
  }

  void _changed() => setState(() {});

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        TextField(
          controller: widget.controller,
          minLines: 4,
          maxLines: 5,
          maxLength: 500,
          decoration: InputDecoration(hintText: widget.hint, counterText: '', contentPadding: const EdgeInsets.fromLTRB(14, 13, 14, 28)),
        ),
        Positioned(
          right: 12,
          bottom: 9,
          child: Text('${widget.controller.text.length.clamp(0, 500)}/500', style: const TextStyle(color: Color(0xFF526486), fontSize: 10.5)),
        ),
      ],
    );
  }
}

class _QuantitySelector extends StatelessWidget {
  final int value;
  final int max;
  final ValueChanged<int> onChanged;
  const _QuantitySelector({required this.value, required this.max, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 48,
      decoration: BoxDecoration(border: Border.all(color: OvanieColors.border), borderRadius: BorderRadius.circular(9)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(onPressed: value > 1 ? () => onChanged(value - 1) : null, icon: const Icon(Icons.remove_rounded, size: 19)),
          SizedBox(width: 42, child: Text('$value', textAlign: TextAlign.center, style: const TextStyle(color: OvanieColors.navy, fontWeight: FontWeight.w800))),
          IconButton(onPressed: value < max ? () => onChanged(value + 1) : null, icon: const Icon(Icons.add_rounded, size: 19)),
        ],
      ),
    );
  }
}

class _MediaRow extends StatelessWidget {
  final List<XFile> photos;
  final VoidCallback onAddPhoto;
  final ValueChanged<int> onRemovePhoto;
  final bool allowVideo;
  final int videoCount;
  final VoidCallback? onAddVideo;
  final bool hasExistingMedia;

  const _MediaRow({
    required this.photos,
    required this.onAddPhoto,
    required this.onRemovePhoto,
    this.allowVideo = false,
    this.videoCount = 0,
    this.onAddVideo,
    this.hasExistingMedia = false,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 112,
      child: ListView(
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        children: [
          if (hasExistingMedia && photos.isEmpty)
            const _ExistingMediaTile(),
          ...List.generate(photos.length, (index) => Padding(
                padding: const EdgeInsets.only(right: 9),
                child: _PhotoTile(file: photos[index], onRemove: () => onRemovePhoto(index)),
              )),
          if (photos.length < 5)
            Padding(
              padding: const EdgeInsets.only(right: 9),
              child: _AddMediaTile(icon: Icons.photo_camera_outlined, label: 'Ajouter\nune photo', onTap: onAddPhoto),
            ),
          if (allowVideo && videoCount == 0)
            _AddMediaTile(icon: Icons.videocam_outlined, label: 'Ajouter\nune vidéo', onTap: onAddVideo ?? () {}),
          if (allowVideo && videoCount > 0)
            const _AddedVideoTile(),
        ],
      ),
    );
  }
}

class _PhotoTile extends StatelessWidget {
  final XFile file;
  final VoidCallback onRemove;
  const _PhotoTile({required this.file, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          width: 94,
          height: 106,
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(color: const Color(0xFFF8FAFD), borderRadius: BorderRadius.circular(8), border: Border.all(color: OvanieColors.border)),
          child: ClipRRect(borderRadius: BorderRadius.circular(6), child: Image.file(File(file.path), fit: BoxFit.cover)),
        ),
        Positioned(
          right: -5,
          top: -5,
          child: InkWell(
            onTap: onRemove,
            child: Container(width: 23, height: 23, decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle, boxShadow: [BoxShadow(color: Color(0x22000000), blurRadius: 4)]), child: const Icon(Icons.close_rounded, size: 17, color: OvanieColors.navy)),
          ),
        ),
      ],
    );
  }
}

class _AddMediaTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _AddMediaTile({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 102,
        height: 106,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: const Color(0xFFFBFDFF), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFFBCD2F5), style: BorderStyle.solid)),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: const Color(0xFF0B45F5), size: 26),
            const SizedBox(height: 8),
            Text(label, textAlign: TextAlign.center, style: const TextStyle(color: OvanieColors.navy, fontSize: 10.7, height: 1.2, fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }
}

class _ExistingMediaTile extends StatelessWidget {
  const _ExistingMediaTile();
  @override
  Widget build(BuildContext context) => Container(
        width: 102,
        height: 106,
        margin: const EdgeInsets.only(right: 9),
        decoration: BoxDecoration(color: const Color(0xFFF1F7FF), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFFBCD2F5))),
        child: const Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.check_circle_rounded, color: OvanieColors.success, size: 28), SizedBox(height: 7), Text('Preuves déjà\nenregistrées', textAlign: TextAlign.center, style: TextStyle(color: OvanieColors.navy, fontSize: 10.5, fontWeight: FontWeight.w700))]),
      );
}

class _AddedVideoTile extends StatelessWidget {
  const _AddedVideoTile();
  @override
  Widget build(BuildContext context) => Container(
        width: 102,
        height: 106,
        decoration: BoxDecoration(color: const Color(0xFFF1F7FF), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFFBCD2F5))),
        child: const Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.videocam_rounded, color: Color(0xFF0B45F5), size: 28), SizedBox(height: 7), Text('Vidéo ajoutée', textAlign: TextAlign.center, style: TextStyle(color: OvanieColors.navy, fontSize: 10.5, fontWeight: FontWeight.w700))]),
      );
}

class _TipCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String text;
  const _TipCard({required this.icon, required this.title, required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(color: const Color(0xFFFFF6E9), borderRadius: BorderRadius.circular(9)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: const Color(0xFFFFA000), size: 26),
          const SizedBox(width: 11),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: Color(0xFFCD7100), fontSize: 13, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(text, style: const TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.4))])),
        ],
      ),
    );
  }
}

class _DateBox extends StatelessWidget {
  final DateTime date;
  final VoidCallback onTap;
  const _DateBox({required this.date, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        height: 50,
        padding: const EdgeInsets.symmetric(horizontal: 13),
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(10), border: Border.all(color: OvanieColors.border)),
        child: Row(children: [const Icon(Icons.calendar_today_outlined, color: OvanieColors.navy, size: 19), const SizedBox(width: 12), Expanded(child: Text(_dateLabel(date), style: const TextStyle(color: OvanieColors.navy, fontSize: 12.5, fontWeight: FontWeight.w600))), const Icon(Icons.keyboard_arrow_down_rounded, color: OvanieColors.navy)]),
      ),
    );
  }
}

class _CollectionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final bool requiredField;
  final String main;
  final String secondary;
  final String? footer;
  final VoidCallback onEdit;

  const _CollectionCard({required this.icon, required this.title, required this.requiredField, required this.main, required this.secondary, this.footer, required this.onEdit});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _FieldLabel(title: title, requiredField: requiredField),
          const SizedBox(height: 12),
          Row(
            children: [
              Container(width: 58, height: 58, decoration: BoxDecoration(color: const Color(0xFFF3F6FC), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: const Color(0xFF0B45F5), size: 33)),
              const SizedBox(width: 14),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(main, style: const TextStyle(color: OvanieColors.navy, fontSize: 13.5, fontWeight: FontWeight.w800)), if (secondary.isNotEmpty) ...[const SizedBox(height: 4), Text(secondary, style: const TextStyle(color: Color(0xFF40547A), fontSize: 11.5, height: 1.35))]])),
              const SizedBox(width: 12),
              OutlinedButton(onPressed: onEdit, style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF0B45F5), side: const BorderSide(color: Color(0xFF0B45F5)), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7))), child: const Text('Modifier', style: TextStyle(fontWeight: FontWeight.w800))),
            ],
          ),
          if (footer != null) ...[const SizedBox(height: 9), Text(footer!, style: const TextStyle(color: Color(0xFF40547A), fontSize: 10.8))],
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final IconData icon;
  final Color tint;
  final Color color;
  final String title;
  final String value;
  final VoidCallback onTap;
  const _SummaryRow({required this.icon, required this.tint, required this.color, required this.title, required this.value, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 43, height: 43, decoration: BoxDecoration(color: tint, shape: BoxShape.circle), child: Icon(icon, color: color, size: 23)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: OvanieColors.navy, fontSize: 12.7, fontWeight: FontWeight.w900)), const SizedBox(height: 5), Text(value.isEmpty ? 'Non renseigné' : value, style: const TextStyle(color: Color(0xFF40547A), fontSize: 11.5, height: 1.35))])),
          const Icon(Icons.chevron_right_rounded, color: OvanieColors.navy),
        ],
        ),
      ),
    );
  }
}

class _SummaryConditions extends StatelessWidget {
  final VoidCallback onTap;
  const _SummaryConditions({required this.onTap});
  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 43, height: 43, decoration: const BoxDecoration(color: Color(0xFFFFF4DC), shape: BoxShape.circle), child: const Icon(Icons.inventory_2_outlined, color: Color(0xFFFFA000), size: 23)),
          const SizedBox(width: 12),
          const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Conditions de retour', style: TextStyle(color: OvanieColors.navy, fontSize: 12.7, fontWeight: FontWeight.w900)), SizedBox(height: 7), _CheckLine('Produit protégé pour le transport'), _CheckLine('Accessoires et éléments fournis regroupés'), _CheckLine('Retour préparé dans les délais')])),
          const Icon(Icons.chevron_right_rounded, color: OvanieColors.navy),
        ],
        ),
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  final String text;
  const _CheckLine(this.text);
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 5),
        child: Row(children: [const Icon(Icons.check_circle_outline_rounded, color: OvanieColors.success, size: 17), const SizedBox(width: 7), Expanded(child: Text(text, style: const TextStyle(color: Color(0xFF40547A), fontSize: 10.8)))]),
      );
}

class _ConfirmNotice extends StatelessWidget {
  const _ConfirmNotice();
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 14),
        decoration: BoxDecoration(color: const Color(0xFFFFF5EE), borderRadius: BorderRadius.circular(9)),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(Icons.shield_outlined, color: OvanieColors.orange, size: 30), SizedBox(width: 12), Expanded(child: Text('En confirmant, vous acceptez les conditions de retour d’OVANIE. Notre équipe traitera votre demande dans les plus brefs délais.', style: TextStyle(color: OvanieColors.navy, fontSize: 11.5, height: 1.45, fontWeight: FontWeight.w500)))]),
      );
}

class _ActionBar extends StatelessWidget {
  final int step;
  final bool submitting;
  final VoidCallback onBack;
  final VoidCallback onContinue;
  const _ActionBar({required this.step, required this.submitting, required this.onBack, required this.onContinue});

  @override
  Widget build(BuildContext context) {
    if (step == 1) {
      return SizedBox(
        height: 52,
        width: double.infinity,
        child: FilledButton(
          onPressed: submitting ? null : onContinue,
          style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0B45F5), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7))),
          child: const Stack(
            alignment: Alignment.center,
            children: [
              Text('Continuer', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800)),
              Align(alignment: Alignment.centerRight, child: Icon(Icons.arrow_forward_rounded)),
            ],
          ),
        ),
      );
    }
    return Row(
      children: [
        Expanded(
          child: SizedBox(
            height: 52,
            child: OutlinedButton.icon(
              onPressed: submitting ? null : onBack,
              style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF0B45F5), side: const BorderSide(color: Color(0xFF0B45F5)), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7))),
              icon: const Icon(Icons.arrow_back_rounded),
              label: const Text('Retour', style: TextStyle(fontWeight: FontWeight.w800)),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: SizedBox(
            height: 52,
            child: FilledButton(
              onPressed: submitting ? null : onContinue,
              style: FilledButton.styleFrom(backgroundColor: step == 4 ? OvanieColors.orange : const Color(0xFF0B45F5), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7))),
              child: submitting
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Row(mainAxisAlignment: MainAxisAlignment.center, children: [Flexible(child: Text(step == 4 ? 'Confirmer le retour' : 'Continuer', maxLines: 1, style: const TextStyle(fontWeight: FontWeight.w800))), const SizedBox(width: 8), Icon(step == 4 ? Icons.check_rounded : Icons.arrow_forward_rounded)]),
            ),
          ),
        ),
      ],
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.error_outline_rounded,
                color: OvanieColors.danger,
                size: 46,
              ),
              const SizedBox(height: 14),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: OvanieColors.text,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Réessayer'),
              ),
            ],
          ),
        ),
      );
}

BoxDecoration _cardDecoration() => BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: OvanieColors.border),
    );

String _dateLabel(DateTime date) {
  const months = <String>[
    'janv.',
    'févr.',
    'mars',
    'avr.',
    'mai',
    'juin',
    'juil.',
    'août',
    'sept.',
    'oct.',
    'nov.',
    'déc.',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}
