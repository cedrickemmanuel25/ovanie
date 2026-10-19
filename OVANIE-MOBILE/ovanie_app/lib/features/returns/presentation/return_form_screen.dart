import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../payments/presentation/payment_ui.dart';
import '../data/returns_repository.dart';
import '../domain/return_model.dart';

/// Parcours « Préparer le retour » en 4 étapes. Il sert aussi bien à une
/// nouvelle demande qu'à la préparation d'un dossier déjà approuvé.
class ReturnFormScreen extends StatefulWidget {
  final int? initialOrderId;
  final int? initialOrderItemId;
  final ClientReturnCase? existingCase;

  const ReturnFormScreen({super.key, this.initialOrderId, this.initialOrderItemId, this.existingCase});

  @override
  State<ReturnFormScreen> createState() => _ReturnFormScreenState();
}

class _ReturnFormScreenState extends State<ReturnFormScreen> {
  final ReturnsRepository _repository = const ReturnsRepository();
  final ImagePicker _picker = ImagePicker();
  final ScrollController _scroll = ScrollController();

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
  String? _stepError;
  int _step = 1;
  int _quantity = 1;
  String _reason = 'Produit endommagé';
  DateTime? _discoveryDate;
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

  static const List<String> _stepTitles = <String>[
    'Informations sur le retour',
    'Détails & preuves',
    'Livraison du retour',
    'Vérification & confirmation',
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _scroll.dispose();
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
        _discoveryDate = full.discoveryDate ?? full.requestDate;
        _pickupDate = full.pickupDate ?? DateTime.now().add(const Duration(days: 2));
        _pickupSlot = full.pickupTimeSlot.trim().isEmpty ? _timeSlots.first : full.pickupTimeSlot;
        if (!_timeSlots.contains(_pickupSlot)) _pickupSlot = _timeSlots.first;
        _storageController.text = full.storageLocation;
        _pickupAddressController.text = full.pickupAddress.trim().isNotEmpty
            ? full.pickupAddress
            : full.collectionAddress;
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
  DateTime? get _purchaseDate => _existing?.orderCreatedAt ?? _order?.createdAt;
  int get _orderedQuantity => _existing?.quantity ?? _item?.orderedQuantity ?? _maxQuantity;
  int get _existingProofs => (_existing?.photoCount ?? 0) + (_existing?.videoCount ?? 0);
  int get _proofCount => _photos.length + _videos.length + _existingProofs;

  Future<void> _pickPhotos() async {
    final room = 5 - _photos.length;
    if (room <= 0) return;
    final picked = await _picker.pickMultiImage(imageQuality: 84);
    if (!mounted || picked.isEmpty) return;
    final merged = <XFile>[..._photos, ...picked];
    final unique = <String, XFile>{for (final file in merged) file.path: file};
    setState(() {
      _photos = unique.values.take(5).toList(growable: false);
      _stepError = null;
    });
  }

  Future<void> _pickVideo() async {
    final picked = await _picker.pickVideo(source: ImageSource.gallery, maxDuration: const Duration(minutes: 1));
    if (!mounted || picked == null) return;
    setState(() {
      _videos = <XFile>[picked];
      _stepError = null;
    });
  }

  String? _validateStep() {
    if (_step == 1) {
      if (_problemController.text.trim().length < 10) {
        return 'Décrivez le problème rencontré en au moins 10 caractères.';
      }
      if (_photos.isEmpty && _existingProofs == 0) {
        return 'Ajoutez au moins une photo claire du produit.';
      }
    }
    if (_step == 2) {
      if (_discoveryDate == null) return 'Indiquez la date à laquelle vous avez constaté le problème.';
      if (_storageController.text.trim().isEmpty) return 'Indiquez où se trouve le produit.';
    }
    if (_step == 3) {
      if (_pickupAddressController.text.trim().isEmpty) return 'Renseignez l’adresse de collecte.';
      if (_contactNameController.text.trim().isEmpty || _contactPhoneController.text.trim().isEmpty) {
        return 'Renseignez le nom et le téléphone du contact pour la collecte.';
      }
    }
    return null;
  }

  void _goTo(int step) {
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _step = step;
      _stepError = null;
    });
    if (_scroll.hasClients) _scroll.jumpTo(0);
  }

  void _continue() {
    final message = _validateStep();
    if (message != null) {
      setState(() => _stepError = message);
      return;
    }
    if (_step < 4) _goTo(_step + 1);
  }

  void _backStep() {
    if (_step > 1) {
      _goTo(_step - 1);
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
      final description = additional.isEmpty || additional == problem ? problem : '$problem\n$additional';
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
      showOvanieToast(context, 'Votre demande de retour a bien été enregistrée.');
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
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: pickup ? _pickupDate : (_discoveryDate ?? now),
      firstDate: pickup ? now : DateTime(2020, 1, 1),
      lastDate: pickup ? now.add(const Duration(days: 45)) : now,
      builder: (context, child) => Theme(
        data: Theme.of(
          context,
        ).copyWith(colorScheme: Theme.of(context).colorScheme.copyWith(primary: OvanieColors.orange)),
        child: child!,
      ),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (pickup) {
        _pickupDate = selected;
      } else {
        _discoveryDate = selected;
      }
      _stepError = null;
    });
  }

  Future<void> _editCollectionAddress() async {
    final value = await _openEditSheet(
      title: 'Adresse de collecte',
      fields: [_SheetField('Adresse complète de collecte', _pickupAddressController.text, lines: 3)],
    );
    if (value != null && mounted) {
      setState(() {
        _pickupAddressController.text = value.first;
        _stepError = null;
      });
    }
  }

  Future<void> _editContact() async {
    final value = await _openEditSheet(
      title: 'Contact pour la collecte',
      fields: [
        _SheetField('Nom complet', _contactNameController.text),
        _SheetField('Téléphone', _contactPhoneController.text, keyboard: TextInputType.phone),
      ],
    );
    if (value != null && mounted) {
      setState(() {
        _contactNameController.text = value[0];
        _contactPhoneController.text = value[1];
        _stepError = null;
      });
    }
  }

  Future<List<String>?> _openEditSheet({required String title, required List<_SheetField> fields}) {
    return showModalBottomSheet<List<String>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (_) => _EditSheet(title: title, fields: fields),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ready = !_loading && !(_error != null && _orderId == null) && _hasSubject;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        bottomNavigationBar: ready ? SafeArea(top: false, child: _actionBar()) : null,
        body: SafeArea(
          child: _loading
              ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
              : _error != null && _orderId == null
              ? _ErrorView(message: _error!, onRetry: _load)
              : _buildContent(),
        ),
      ),
    );
  }

  bool get _hasSubject => _existing != null || (_center?.eligibleOrders.isNotEmpty ?? false);

  Widget _actionBar() {
    final last = _step == 4;
    final continueButton = PaymentButton(
      label: last ? 'Confirmer le retour' : 'Continuer',
      loading: _submitting,
      height: 56,
      onPressed: last ? _submit : _continue,
    );
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: payBorder)),
      ),
      child: _step == 1
          ? continueButton
          : Row(
              children: [
                Expanded(
                  child: PaymentButton(label: 'Retour', outlined: true, height: 56, onPressed: _backStep),
                ),
                const SizedBox(width: 14),
                Expanded(child: continueButton),
              ],
            ),
    );
  }

  Widget _buildContent() {
    if (!_hasSubject) {
      return Column(
        children: [
          _ReturnHeader(step: _step, title: _stepTitles[_step - 1], onBack: () => Navigator.of(context).maybePop()),
          const Expanded(
            child: Center(
              child: Padding(
                padding: EdgeInsets.all(32),
                child: Text(
                  'Aucun article n’est actuellement éligible à un retour.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: payMuted, fontSize: 16, height: 1.45),
                ),
              ),
            ),
          ),
        ],
      );
    }

    const intros = <String>[
      'Remplissez les informations ci-dessous pour initier votre demande de retour. Notre équipe vous guidera ensuite pour la suite du processus.',
      'Ajoutez autant de détails et de preuves que possible afin d’accélérer le traitement de votre demande.',
      'OVANIE organisera la récupération de votre retour directement à l’adresse renseignée ci-dessous.',
      'Vérifiez les informations ci-dessous avant de confirmer votre demande de retour.',
    ];

    return Column(
      children: [
        _ReturnHeader(step: _step, title: _stepTitles[_step - 1], onBack: _backStep),
        Expanded(
          child: ListView(
            controller: _scroll,
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
            children: [
              _StepProgress(step: _step, titles: _stepTitles),
              const SizedBox(height: 16),
              Text(intros[_step - 1], style: const TextStyle(color: payNavy, fontSize: 15.5, height: 1.45)),
              const SizedBox(height: 16),
              if (_step == 1) _buildStepOne(),
              if (_step == 2) _buildStepTwo(),
              if (_step == 3) _buildStepThree(),
              if (_step == 4) _buildStepFour(),
              if (_stepError != null) ...[const SizedBox(height: 14), _ErrorBanner(message: _stepError!)],
              if (_error != null) ...[const SizedBox(height: 14), _ErrorBanner(message: _error!)],
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildStepOne() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Panel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Produit concerné', style: _Text.section),
              const SizedBox(height: 14),
              _ProductRow(
                name: _productName,
                orderNumber: _orderNumber,
                purchasedAt: _purchaseDate,
                imageUrl: _imageUrl,
                quantity: _orderedQuantity,
              ),
            ],
          ),
        ),
        const SizedBox(height: 22),
        const Text('Motif du retour', style: _Text.section),
        const SizedBox(height: 12),
        _ReasonGrid(value: _reason, items: _reasons, onChanged: (value) => setState(() => _reason = value)),
        const SizedBox(height: 22),
        const Text('Description détaillée', style: _Text.section),
        const SizedBox(height: 12),
        _CountedField(
          controller: _problemController,
          hint: 'Expliquez le problème rencontré en quelques mots',
          onChanged: () => setState(() => _stepError = null),
        ),
        const SizedBox(height: 18),
        const Text('Quantité à retourner', style: _Text.section),
        const SizedBox(height: 4),
        const Text('Sélectionnez la quantité que vous souhaitez retourner', style: _Text.hint),
        const SizedBox(height: 12),
        Row(
          children: [
            _QuantityStepper(
              value: _quantity,
              max: _maxQuantity,
              onChanged: (value) => setState(() => _quantity = value),
            ),
            const SizedBox(width: 18),
            Text('sur $_orderedQuantity acheté(s)', style: const TextStyle(color: payMuted, fontSize: 15)),
          ],
        ),
        const SizedBox(height: 24),
        const Text('Photos du produit (obligatoire)', style: _Text.section),
        const SizedBox(height: 4),
        const Text('Ajoutez des photos claires du problème (max. 5 photos)', style: _Text.hint),
        const SizedBox(height: 12),
        _MediaGrid(
          photos: _photos,
          videos: const [],
          existing: _existing?.proofMedia ?? const [],
          onRemovePhoto: (index) => setState(() => _photos = [..._photos]..removeAt(index)),
          onRemoveVideo: () {},
          addTiles: [
            if (_photos.length < 5)
              _AddTile(icon: Icons.photo_camera_outlined, label: 'Ajouter une photo', onTap: _pickPhotos),
          ],
        ),
        const SizedBox(height: 16),
        const _InfoBox(
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
        const Text('Précisions (facultatif)', style: _Text.section),
        const SizedBox(height: 12),
        _CountedField(
          controller: _detailsController,
          hint: 'Ajoutez les détails utiles pour comprendre le problème.',
          minLines: 4,
          onChanged: () {},
        ),
        const SizedBox(height: 22),
        const Text('Date du problème', style: _Text.section),
        const SizedBox(height: 4),
        const Text('Indiquez quand vous avez constaté le problème', style: _Text.hint),
        const SizedBox(height: 12),
        _PickerBox(
          leading: Icons.calendar_month_outlined,
          text: _discoveryDate == null ? 'Sélectionner une date' : _longDate(_discoveryDate!),
          placeholder: _discoveryDate == null,
          trailing: Icons.keyboard_arrow_down_rounded,
          onTap: () => _pickDate(pickup: false),
        ),
        const SizedBox(height: 22),
        const Text('Où se trouve le produit', style: _Text.section),
        const SizedBox(height: 4),
        const Text('Où le produit était-il stocké lorsque vous avez constaté le problème ?', style: _Text.hint),
        const SizedBox(height: 12),
        TextField(
          controller: _storageController,
          onChanged: (_) => setState(() => _stepError = null),
          style: const TextStyle(color: payNavy, fontSize: 16),
          decoration: _inputDecoration(
            hint: 'Ex. Chantier – commune, quartier',
            prefix: const Icon(Icons.location_on_outlined, color: payNavy, size: 26),
          ),
        ),
        const SizedBox(height: 24),
        const Text('Photos / vidéos du problème (obligatoire)', style: _Text.section),
        const SizedBox(height: 4),
        const Text('Ajoutez des photos ou vidéos claires (max. 5 fichiers)', style: _Text.hint),
        const SizedBox(height: 12),
        _MediaGrid(
          photos: _photos,
          videos: _videos,
          existing: _existing?.proofMedia ?? const [],
          onRemovePhoto: (index) => setState(() => _photos = [..._photos]..removeAt(index)),
          onRemoveVideo: () => setState(() => _videos = const []),
          addTiles: [
            if (_photos.length < 5)
              _AddTile(icon: Icons.photo_camera_outlined, label: 'Ajouter une photo', onTap: _pickPhotos),
            if (_videos.isEmpty) _AddTile(icon: Icons.videocam_outlined, label: 'Ajouter une vidéo', onTap: _pickVideo),
          ],
        ),
        const SizedBox(height: 16),
        const _InfoBox(
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
        _CollectCard(
          title: 'Adresse de collecte',
          icon: Icons.location_on_outlined,
          lines: _addressLines(_pickupAddressController.text),
          placeholder: 'Adresse à renseigner',
          footer: 'Adresse utilisée pour la récupération du produit.',
          onEdit: _editCollectionAddress,
        ),
        const SizedBox(height: 16),
        _CollectCard(
          title: 'Contact pour la collecte',
          icon: Icons.call_outlined,
          lines: [
            _contactNameController.text.trim(),
            if (_contactPhoneController.text.trim().isNotEmpty)
              formatCiPhoneDisplay(_contactPhoneController.text, includeCountryCode: false),
          ].where((line) => line.isNotEmpty).toList(growable: false),
          placeholder: 'Contact à renseigner',
          footer: 'Notre équipe vous contactera pour organiser la récupération.',
          onEdit: _editContact,
        ),
        const SizedBox(height: 24),
        const Text('Date souhaitée de collecte', style: _Text.section),
        const SizedBox(height: 12),
        _PickerBox(
          text: _longDate(_pickupDate),
          trailing: Icons.calendar_month_outlined,
          onTap: () => _pickDate(pickup: true),
        ),
        const SizedBox(height: 24),
        const Text('Créneau horaire préféré', style: _Text.section),
        const SizedBox(height: 12),
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (var i = 0; i < _timeSlots.length; i++) ...[
                Expanded(
                  child: _SlotTile(
                    label: _timeSlots[i],
                    selected: _pickupSlot == _timeSlots[i],
                    onTap: () => setState(() => _pickupSlot = _timeSlots[i]),
                  ),
                ),
                if (i != _timeSlots.length - 1) const SizedBox(width: 8),
              ],
            ],
          ),
        ),
        const SizedBox(height: 18),
        const _InfoBox(
          title: 'Conseil',
          text: 'Emballez le produit aussi bien que possible et gardez-le accessible pour faciliter la collecte.',
        ),
      ],
    );
  }

  Widget _buildStepFour() {
    final description = [
      _problemController.text.trim(),
      _detailsController.text.trim(),
    ].where((part) => part.isNotEmpty).toSet().join('\n');
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Panel(
          color: Colors.white,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Récapitulatif du retour', style: _Text.section),
              const SizedBox(height: 14),
              _ProductRow(
                name: _productName,
                orderNumber: _orderNumber,
                purchasedAt: _purchaseDate,
                imageUrl: _imageUrl,
                quantity: _quantity,
                quantityLabel: 'Qté retournée',
              ),
              const SizedBox(height: 10),
              _SummaryLine(label: 'Motif du retour', value: _reason),
              _SummaryLine(label: 'Description du problème', value: description),
              _SummaryLine(
                label: 'Date de découverte du problème',
                value: _discoveryDate == null ? '—' : paymentShortDate(_discoveryDate),
              ),
              const _SummaryLine(label: 'Mode de retour', value: 'Collecte à l’adresse indiquée par OVANIE'),
              const Divider(height: 1, color: payBorder),
              const SizedBox(height: 14),
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Preuves fournies',
                      style: TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                  ),
                  Text('$_proofCount fichier(s)', style: const TextStyle(color: payMuted, fontSize: 14.5)),
                ],
              ),
              const SizedBox(height: 10),
              _MediaGrid(
                photos: _photos,
                videos: _videos,
                existing: _existing?.proofMedia ?? const [],
                readOnly: true,
                onRemovePhoto: (_) {},
                onRemoveVideo: () {},
                addTiles: const [],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _Panel(
          color: Colors.white,
          child: Column(
            children: [
              _ReviewContact(
                icon: Icons.location_on_outlined,
                title: 'Adresse de collecte',
                lines: [..._addressLines(_pickupAddressController.text), '${_longDate(_pickupDate)} · $_pickupSlot'],
                footer: 'Adresse utilisée pour la récupération du produit.',
                onEdit: () => _goTo(3),
              ),
              const Divider(height: 28, color: payBorder),
              _ReviewContact(
                icon: Icons.person_outline_rounded,
                title: 'Contact pour la collecte',
                lines: [
                  _contactNameController.text.trim(),
                  formatCiPhoneDisplay(_contactPhoneController.text, includeCountryCode: false),
                ],
                footer: 'Notre équipe vous contactera pour organiser la récupération.',
                onEdit: () => _goTo(3),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const _Panel(
          color: Colors.white,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Conditions de retour', style: _Text.section),
              SizedBox(height: 8),
              _ConditionLine(
                title: 'Produit protégé pour le transport',
                text: 'Emballez le produit dans son emballage d’origine si possible.',
              ),
              _ConditionLine(
                title: 'Accessoires et éléments fournis regroupés',
                text: 'Incluez tous les accessoires, notices et éléments fournis.',
              ),
              _ConditionLine(
                title: 'Retour préparé dans les délais',
                text: 'Le produit doit être prêt pour la collecte à la date convenue.',
                last: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const _InfoBox(
          title: 'En confirmant, vous acceptez les conditions de retour d’OVANIE.',
          text: 'Notre équipe traitera votre demande dans les plus brefs délais.',
        ),
      ],
    );
  }
}

List<String> _addressLines(String address) {
  final parts = address.split(',').map((part) => part.trim()).where((part) => part.isNotEmpty).toList(growable: false);
  if (parts.length <= 1) return parts;
  return [parts.first, parts.skip(1).join(', ')];
}

String _longDate(DateTime date) {
  const months = [
    'janvier',
    'février',
    'mars',
    'avril',
    'mai',
    'juin',
    'juillet',
    'août',
    'septembre',
    'octobre',
    'novembre',
    'décembre',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

InputDecoration _inputDecoration({required String hint, Widget? prefix}) => InputDecoration(
  hintText: hint,
  hintStyle: const TextStyle(color: Color(0xFF8A93A8), fontSize: 16),
  prefixIcon: prefix,
  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
  enabledBorder: OutlineInputBorder(
    borderRadius: BorderRadius.circular(12),
    borderSide: const BorderSide(color: Color(0xFFD5DCE6)),
  ),
  focusedBorder: OutlineInputBorder(
    borderRadius: BorderRadius.circular(12),
    borderSide: const BorderSide(color: OvanieColors.orange, width: 1.4),
  ),
);

class _Text {
  static const section = TextStyle(color: payNavy, fontSize: 19, fontWeight: FontWeight.w800);
  static const hint = TextStyle(color: payMuted, fontSize: 14.5);
}

class _ReturnHeader extends StatelessWidget {
  final int step;
  final String title;
  final VoidCallback onBack;

  const _ReturnHeader({required this.step, required this.title, required this.onBack});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(6, 10, 16, 12),
    child: Row(
      children: [
        IconButton(
          onPressed: onBack,
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_rounded, color: payNavy, size: 30),
        ),
        const SizedBox(width: 6),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Préparer le retour',
                style: TextStyle(color: payNavy, fontSize: 25, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 2),
              Text('Étape $step sur 4 • $title', style: const TextStyle(color: payMuted, fontSize: 15)),
            ],
          ),
        ),
      ],
    ),
  );
}

class _StepProgress extends StatelessWidget {
  final int step;
  final List<String> titles;

  const _StepProgress({required this.step, required this.titles});

  @override
  Widget build(BuildContext context) {
    const grey = Color(0xFFE6E9EE);
    Widget dot(int number) {
      final done = number < step;
      final current = number == step;
      return Container(
        width: 38,
        height: 38,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: done || current ? OvanieColors.orange : grey,
          shape: BoxShape.circle,
          border: current && step == 4 ? Border.all(color: const Color(0xFFFFD2B8), width: 3) : null,
        ),
        child: done
            ? const Icon(Icons.check_rounded, color: Colors.white, size: 22)
            : Text(
                '$number',
                style: TextStyle(color: current ? Colors.white : payMuted, fontSize: 16, fontWeight: FontWeight.w700),
              ),
      );
    }

    Widget line(int after) {
      final full = after < step;
      return Expanded(
        child: Container(
          height: 3,
          margin: const EdgeInsets.symmetric(horizontal: 4),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: full
                  ? const [OvanieColors.orange, OvanieColors.orange]
                  : after == step
                  ? const [OvanieColors.orange, grey]
                  : const [grey, grey],
              stops: after == step ? const [.5, .5] : null,
            ),
          ),
        ),
      );
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Row(children: [dot(1), line(1), dot(2), line(2), dot(3), line(3), dot(4)]),
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (var i = 0; i < titles.length; i++)
              Expanded(
                child: Text(
                  titles[i].replaceFirst(' sur ', '\nsur ').replaceFirst(' & ', ' &\n'),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: i + 1 == step ? OvanieColors.orange : payMuted,
                    fontSize: 12.5,
                    height: 1.25,
                    fontWeight: i + 1 == step ? FontWeight.w700 : FontWeight.w400,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _Panel extends StatelessWidget {
  final Widget child;
  final Color color;

  const _Panel({required this.child, this.color = Colors.white});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
    decoration: BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: payBorder),
    ),
    child: child,
  );
}

class _ProductRow extends StatelessWidget {
  final String name;
  final String orderNumber;
  final DateTime? purchasedAt;
  final String imageUrl;
  final int quantity;
  final String quantityLabel;

  const _ProductRow({
    required this.name,
    required this.orderNumber,
    required this.purchasedAt,
    required this.imageUrl,
    required this.quantity,
    this.quantityLabel = 'Qté',
  });

  @override
  Widget build(BuildContext context) => Row(
    children: [
      OrderItemThumbnail(url: imageUrl, width: 112, height: 88),
      const SizedBox(width: 16),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: payNavy, fontSize: 16.5, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            if (orderNumber.isNotEmpty) Text('Commande #$orderNumber', style: _Text.hint),
            if (purchasedAt != null) Text('Acheté le ${paymentShortDate(purchasedAt)}', style: _Text.hint),
            Text('$quantityLabel : $quantity', style: _Text.hint),
          ],
        ),
      ),
    ],
  );
}

class _RadioMark extends StatelessWidget {
  final bool selected;

  const _RadioMark({required this.selected});

  @override
  Widget build(BuildContext context) => Container(
    width: 26,
    height: 26,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: selected ? OvanieColors.orange : const Color(0xFF9AA3B2), width: selected ? 2.2 : 1.5),
    ),
    child: selected
        ? Container(
            width: 14,
            height: 14,
            decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
          )
        : null,
  );
}

class _ReasonGrid extends StatelessWidget {
  final String value;
  final List<String> items;
  final ValueChanged<String> onChanged;

  const _ReasonGrid({required this.value, required this.items, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    Widget tile(String item) {
      final selected = item == value;
      return InkWell(
        onTap: () => onChanged(item),
        borderRadius: BorderRadius.circular(12),
        child: Container(
          height: 62,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            color: selected ? const Color(0xFFFFF7F2) : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: selected ? OvanieColors.orange : payBorder, width: selected ? 1.6 : 1),
          ),
          child: Row(
            children: [
              _RadioMark(selected: selected),
              const SizedBox(width: 10),
              Expanded(
                child: Text(item, maxLines: 2, style: const TextStyle(color: payNavy, fontSize: 14.5, height: 1.2)),
              ),
            ],
          ),
        ),
      );
    }

    final rows = <Widget>[];
    for (var i = 0; i < items.length; i += 2) {
      final pair = items.skip(i).take(2).toList(growable: false);
      rows.add(
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: pair.length == 2
              ? Row(
                  children: [
                    Expanded(child: tile(pair[0])),
                    const SizedBox(width: 10),
                    Expanded(child: tile(pair[1])),
                  ],
                )
              : tile(pair[0]),
        ),
      );
    }
    return Column(children: rows);
  }
}

class _CountedField extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final int minLines;
  final VoidCallback onChanged;

  const _CountedField({required this.controller, required this.hint, required this.onChanged, this.minLines = 3});

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.end,
    children: [
      TextField(
        controller: controller,
        minLines: minLines,
        maxLines: 7,
        maxLength: 500,
        onChanged: (_) => onChanged(),
        style: const TextStyle(color: payNavy, fontSize: 15.5, height: 1.4),
        decoration: _inputDecoration(hint: hint).copyWith(counterText: ''),
      ),
      const SizedBox(height: 6),
      ValueListenableBuilder<TextEditingValue>(
        valueListenable: controller,
        builder: (_, value, __) =>
            Text('${value.text.characters.length}/500', style: const TextStyle(color: payMuted, fontSize: 14)),
      ),
    ],
  );
}

class _QuantityStepper extends StatelessWidget {
  final int value;
  final int max;
  final ValueChanged<int> onChanged;

  const _QuantityStepper({required this.value, required this.max, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    Widget button(IconData icon, VoidCallback? onTap) => InkWell(
      onTap: onTap,
      child: SizedBox(
        width: 62,
        height: 58,
        child: Icon(icon, color: onTap == null ? const Color(0xFFB9C2D0) : payNavy, size: 26),
      ),
    );
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF4F6F9),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: payBorder),
      ),
      clipBehavior: Clip.antiAlias,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          button(Icons.remove_rounded, value > 1 ? () => onChanged(value - 1) : null),
          Container(
            width: 84,
            height: 58,
            color: Colors.white,
            alignment: Alignment.center,
            child: Text(
              '$value',
              style: const TextStyle(color: payNavy, fontSize: 19, fontWeight: FontWeight.w600),
            ),
          ),
          button(Icons.add_rounded, value < max ? () => onChanged(value + 1) : null),
        ],
      ),
    );
  }
}

class _AddTile {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _AddTile({required this.icon, required this.label, required this.onTap});
}

/// Grille des preuves : photos et vidéo choisies, preuves déjà envoyées,
/// puis les cases d'ajout. Trois cases par ligne.
class _MediaGrid extends StatelessWidget {
  final List<XFile> photos;
  final List<XFile> videos;
  final List<ReturnProofMedia> existing;
  final List<_AddTile> addTiles;
  final bool readOnly;
  final ValueChanged<int> onRemovePhoto;
  final VoidCallback onRemoveVideo;

  const _MediaGrid({
    required this.photos,
    required this.videos,
    required this.existing,
    required this.addTiles,
    required this.onRemovePhoto,
    required this.onRemoveVideo,
    this.readOnly = false,
  });

  @override
  Widget build(BuildContext context) {
    final tiles = <Widget>[
      for (final media in existing) _MediaTile(networkUrl: media.video ? null : media.url, video: media.video),
      for (var i = 0; i < photos.length; i++)
        _MediaTile(file: File(photos[i].path), onRemove: readOnly ? null : () => onRemovePhoto(i)),
      if (videos.isNotEmpty) _MediaTile(video: true, onRemove: readOnly ? null : onRemoveVideo, label: 'Vidéo ajoutée'),
      for (final add in addTiles) _AddMediaTile(tile: add),
    ];
    if (tiles.isEmpty) {
      return const Text('Aucune preuve ajoutée.', style: TextStyle(color: payMuted, fontSize: 14.5));
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        final size = (constraints.maxWidth - 2 * 12) / 3;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [for (final tile in tiles) SizedBox(width: size, height: size * .86, child: tile)],
        );
      },
    );
  }
}

class _MediaTile extends StatelessWidget {
  final File? file;
  final String? networkUrl;
  final bool video;
  final String? label;
  final VoidCallback? onRemove;

  const _MediaTile({this.file, this.networkUrl, this.video = false, this.label, this.onRemove});

  @override
  Widget build(BuildContext context) {
    final Widget image;
    if (file != null) {
      image = Image.file(file!, fit: BoxFit.cover, cacheWidth: 360);
    } else if (networkUrl != null) {
      image = Image.network(
        networkUrl!,
        fit: BoxFit.cover,
        cacheWidth: 360,
        errorBuilder: (_, __, ___) => const Icon(Icons.image_outlined, color: Color(0xFF8A93A8)),
      );
    } else {
      image = Container(color: const Color(0xFF3A4150));
    }
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Stack(
        fit: StackFit.expand,
        children: [
          image,
          if (video)
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
                color: const Color(0xB3000000),
                child: Row(
                  children: [
                    const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 22),
                    const SizedBox(width: 4),
                    Expanded(
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: Alignment.centerLeft,
                        child: Text(label ?? 'Vidéo', style: const TextStyle(color: Colors.white, fontSize: 13)),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          if (onRemove != null)
            Positioned(
              top: 6,
              right: 6,
              child: Material(
                color: Colors.white,
                shape: const CircleBorder(),
                elevation: 2,
                child: InkWell(
                  customBorder: const CircleBorder(),
                  onTap: onRemove,
                  child: const SizedBox(
                    width: 30,
                    height: 30,
                    child: Icon(Icons.close_rounded, color: payNavy, size: 20),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _AddMediaTile extends StatelessWidget {
  final _AddTile tile;

  const _AddMediaTile({required this.tile});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: tile.onTap,
    borderRadius: BorderRadius.circular(10),
    child: CustomPaint(
      painter: const _DashedPainter(),
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(tile.icon, color: payNavy, size: 32),
            const SizedBox(height: 8),
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(tile.label, style: const TextStyle(color: Color(0xFF4A556E), fontSize: 14)),
            ),
          ],
        ),
      ),
    ),
  );
}

class _DashedPainter extends CustomPainter {
  const _DashedPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFFC3CBD8)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.3;
    final path = Path()..addRRect(RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(10)));
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        canvas.drawPath(metric.extractPath(distance, distance + 6), paint);
        distance += 10;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _InfoBox extends StatelessWidget {
  final String title;
  final String text;

  const _InfoBox({required this.title, required this.text});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
    decoration: BoxDecoration(color: const Color(0xFFE8F1FC), borderRadius: BorderRadius.circular(12)),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.info_outline_rounded, color: payNavy, size: 30),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(color: Color(0xFF0B4FB3), fontSize: 16, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 3),
              Text(text, style: const TextStyle(color: Color(0xFF4A556E), fontSize: 14.5, height: 1.4)),
            ],
          ),
        ),
      ],
    ),
  );
}

class _ErrorBanner extends StatelessWidget {
  final String message;

  const _ErrorBanner({required this.message});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
    decoration: BoxDecoration(
      color: const Color(0xFFFFEEEE),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: const Color(0xFFF7B7B7)),
    ),
    child: Row(
      children: [
        const Icon(Icons.error_rounded, color: payRed, size: 28),
        const SizedBox(width: 12),
        Expanded(
          child: Text(message, style: const TextStyle(color: payRed, fontSize: 15)),
        ),
      ],
    ),
  );
}

class _PickerBox extends StatelessWidget {
  final IconData? leading;
  final String text;
  final bool placeholder;
  final IconData trailing;
  final VoidCallback onTap;

  const _PickerBox({
    required this.text,
    required this.trailing,
    required this.onTap,
    this.leading,
    this.placeholder = false,
  });

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Container(
      height: 64,
      padding: const EdgeInsets.symmetric(horizontal: 18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFD5DCE6)),
      ),
      child: Row(
        children: [
          if (leading != null) ...[Icon(leading, color: payNavy, size: 28), const SizedBox(width: 16)],
          Expanded(
            child: Text(text, style: TextStyle(color: placeholder ? payMuted : payNavy, fontSize: 17)),
          ),
          Icon(trailing, color: payNavy, size: 28),
        ],
      ),
    ),
  );
}

class _CollectCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final List<String> lines;
  final String placeholder;
  final String footer;
  final VoidCallback onEdit;

  const _CollectCard({
    required this.title,
    required this.icon,
    required this.lines,
    required this.placeholder,
    required this.footer,
    required this.onEdit,
  });

  @override
  Widget build(BuildContext context) => _Panel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(child: Text(title, style: _Text.section)),
            InkWell(
              onTap: onEdit,
              child: const Padding(
                padding: EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                child: Text('Modifier', style: TextStyle(color: OvanieColors.orange, fontSize: 16.5)),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Container(
              width: 62,
              height: 62,
              decoration: const BoxDecoration(color: Color(0xFFF1F3F6), shape: BoxShape.circle),
              child: Icon(icon, color: payNavy, size: 30),
            ),
            const SizedBox(width: 18),
            Expanded(
              child: lines.isEmpty
                  ? Text(placeholder, style: const TextStyle(color: OvanieColors.orange, fontSize: 16))
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          lines.first,
                          style: const TextStyle(color: payNavy, fontSize: 16.5, fontWeight: FontWeight.w700),
                        ),
                        for (final line in lines.skip(1))
                          Padding(
                            padding: const EdgeInsets.only(top: 3),
                            child: Text(line, style: const TextStyle(color: Color(0xFF4A556E), fontSize: 15.5)),
                          ),
                      ],
                    ),
            ),
          ],
        ),
        const Divider(height: 28, color: payBorder),
        Text(footer, style: const TextStyle(color: payMuted, fontSize: 14.5)),
      ],
    ),
  );
}

class _SlotTile extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _SlotTile({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final parts = label.split(' (');
    final title = parts.first;
    final hours = parts.length > 1 ? '(${parts[1]}' : '';
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.fromLTRB(10, 14, 8, 14),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFFFF7F2) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? OvanieColors.orange : payBorder, width: selected ? 1.6 : 1),
        ),
        child: Row(
          children: [
            _RadioMark(selected: selected),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final line in [title, hours])
                    if (line.isNotEmpty)
                      FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: Alignment.centerLeft,
                        child: Text(
                          line,
                          maxLines: 1,
                          style: TextStyle(
                            color: selected ? OvanieColors.orange : const Color(0xFF4A556E),
                            fontSize: 14,
                            height: 1.3,
                          ),
                        ),
                      ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryLine extends StatelessWidget {
  final String label;
  final String value;

  const _SummaryLine({required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 11),
    decoration: const BoxDecoration(
      border: Border(top: BorderSide(color: payBorder)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(label, style: const TextStyle(color: payMuted, fontSize: 14.5)),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            value.trim().isEmpty ? '—' : value,
            style: const TextStyle(color: payNavy, fontSize: 14.5, height: 1.4),
          ),
        ),
      ],
    ),
  );
}

class _ReviewContact extends StatelessWidget {
  final IconData icon;
  final String title;
  final List<String> lines;
  final String footer;
  final VoidCallback onEdit;

  const _ReviewContact({
    required this.icon,
    required this.title,
    required this.lines,
    required this.footer,
    required this.onEdit,
  });

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 52,
        height: 52,
        decoration: const BoxDecoration(color: Color(0xFFEAF1FB), shape: BoxShape.circle),
        child: Icon(icon, color: payNavy, size: 28),
      ),
      const SizedBox(width: 14),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            for (final line in lines.where((line) => line.trim().isNotEmpty))
              Text(line, style: const TextStyle(color: Color(0xFF4A556E), fontSize: 14.5, height: 1.4)),
            const SizedBox(height: 4),
            Text(footer, style: const TextStyle(color: payMuted, fontSize: 13)),
          ],
        ),
      ),
      InkWell(
        onTap: onEdit,
        child: const Text(
          'Modifier',
          style: TextStyle(
            color: payBlue,
            fontSize: 15,
            decoration: TextDecoration.underline,
            decorationColor: payBlue,
          ),
        ),
      ),
    ],
  );
}

class _ConditionLine extends StatelessWidget {
  final String title;
  final String text;
  final bool last;

  const _ConditionLine({required this.title, required this.text, this.last = false});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 11),
    decoration: BoxDecoration(
      border: last ? null : const Border(bottom: BorderSide(color: payBorder)),
    ),
    child: Row(
      children: [
        const CircleAvatar(
          radius: 14,
          backgroundColor: payGreen,
          child: Icon(Icons.check_rounded, color: Colors.white, size: 18),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(color: payNavy, fontSize: 15)),
              const SizedBox(height: 2),
              Text(text, style: const TextStyle(color: payMuted, fontSize: 13)),
            ],
          ),
        ),
      ],
    ),
  );
}

class _SheetField {
  final String label;
  final String initial;
  final int lines;
  final TextInputType keyboard;

  const _SheetField(this.label, this.initial, {this.lines = 1, this.keyboard = TextInputType.text});
}

/// Fenêtre du bas de modification (adresse ou contact de collecte).
class _EditSheet extends StatefulWidget {
  final String title;
  final List<_SheetField> fields;

  const _EditSheet({required this.title, required this.fields});

  @override
  State<_EditSheet> createState() => _EditSheetState();
}

class _EditSheetState extends State<_EditSheet> {
  late final List<TextEditingController> _controllers = [
    for (final field in widget.fields) TextEditingController(text: field.initial),
  ];

  @override
  void dispose() {
    for (final controller in _controllers) {
      controller.dispose();
    }
    super.dispose();
  }

  bool get _valid => _controllers.every((controller) => controller.text.trim().isNotEmpty);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(22, 10, 22, 12 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 46,
              height: 5,
              decoration: BoxDecoration(color: const Color(0xFFD0D6DF), borderRadius: BorderRadius.circular(3)),
            ),
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: Text(
                  widget.title,
                  style: const TextStyle(color: payNavy, fontSize: 25, fontWeight: FontWeight.w800),
                ),
              ),
              IconButton(
                onPressed: () => Navigator.of(context).pop(),
                tooltip: 'Fermer',
                icon: const Icon(Icons.close_rounded, color: payNavy, size: 32),
              ),
            ],
          ),
          for (var i = 0; i < widget.fields.length; i++) ...[
            const SizedBox(height: 16),
            Text(
              widget.fields[i].label,
              style: const TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _controllers[i],
              autofocus: i == 0,
              minLines: widget.fields[i].lines,
              maxLines: widget.fields[i].lines + 1,
              keyboardType: widget.fields[i].lines > 1 ? TextInputType.multiline : widget.fields[i].keyboard,
              onChanged: (_) => setState(() {}),
              style: const TextStyle(color: payNavy, fontSize: 16),
              decoration: _inputDecoration(hint: '').copyWith(filled: true, fillColor: const Color(0xFFF7F8FA)),
            ),
          ],
          const SizedBox(height: 22),
          PaymentButton(
            label: 'Enregistrer',
            height: 56,
            onPressed: _valid
                ? () => Navigator.of(context).pop([for (final controller in _controllers) controller.text.trim()])
                : null,
          ),
          Center(
            child: TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text(
                'Annuler',
                style: TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w600),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 100,
            height: 100,
            decoration: const BoxDecoration(color: paySoft, shape: BoxShape.circle),
            child: const Icon(Icons.cloud_off_rounded, color: Color(0xFF4A556E), size: 46),
          ),
          const SizedBox(height: 18),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: payNavy, fontSize: 16),
          ),
          const SizedBox(height: 18),
          PaymentButton(label: 'Réessayer', onPressed: onRetry),
        ],
      ),
    ),
  );
}
