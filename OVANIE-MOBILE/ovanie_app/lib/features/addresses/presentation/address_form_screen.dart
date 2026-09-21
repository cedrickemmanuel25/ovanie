import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/reference_data/reference_data_store.dart';
import '../../../core/platform/device_location.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../checkout/data/geo_repository.dart';
import '../data/addresses_repository.dart';
import '../domain/client_address.dart';
import 'address_map_picker_screen.dart';

class AddressFormScreen extends StatefulWidget {
  final ClientAddress? existing;
  final List<AddressTypeOption> types;
  final bool startWithCurrentPosition;

  const AddressFormScreen({
    super.key,
    this.existing,
    this.types = const [],
    this.startWithCurrentPosition = false,
  });

  @override
  State<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends State<AddressFormScreen> {
  final _repository = const AddressesRepository();
  final _geo = const GeoRepository();
  final _formKey = GlobalKey<FormState>();

  late final TextEditingController _label;
  late final TextEditingController _city;
  late final TextEditingController _commune;
  late final TextEditingController _quartier;
  late final TextEditingController _subQuarter;
  late final TextEditingController _fullAddress;
  late final TextEditingController _landmark;
  late final TextEditingController _contactName;
  late final TextEditingController _contactPhone;

  String _type = 'home';
  double? _latitude;
  double? _longitude;
  bool _isDefault = false;
  bool _saving = false;
  bool _locating = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    final existingType = existing?.type ?? 'home';
    _type = const {'home', 'office', 'site', 'warehouse', 'other'}.contains(existingType)
        ? existingType
        : 'home';

    _label = TextEditingController(text: existing?.label ?? '');
    _city = TextEditingController(text: existing?.city.isNotEmpty == true ? existing!.city : 'Abidjan');
    _commune = TextEditingController(text: existing?.commune ?? '');
    _quartier = TextEditingController(
      text: existing?.quartierPrincipal.isNotEmpty == true
          ? existing!.quartierPrincipal
          : (existing?.quartier ?? ''),
    );
    _subQuarter = TextEditingController(text: existing?.sousQuartier ?? '');
    _fullAddress = TextEditingController(text: existing?.address ?? '');
    _landmark = TextEditingController();
    _contactName = TextEditingController(
      text: existing?.recipientName.isNotEmpty == true
          ? existing!.recipientName
          : (SessionStore.instance.name ?? ''),
    );
    _contactPhone = TextEditingController(
      text: formatCiPhoneDisplay(
        existing?.phone.isNotEmpty == true
            ? existing!.phone
            : (SessionStore.instance.phone ?? ''),
      ),
    );
    _latitude = existing?.latitude;
    _longitude = existing?.longitude;
    _isDefault = existing?.isDefault ?? false;

    if (widget.startWithCurrentPosition && existing == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _useCurrentPosition());
    }
  }

  @override
  void dispose() {
    _label.dispose();
    _city.dispose();
    _commune.dispose();
    _quartier.dispose();
    _subQuarter.dispose();
    _fullAddress.dispose();
    _landmark.dispose();
    _contactName.dispose();
    _contactPhone.dispose();
    super.dispose();
  }

  Future<void> _useCurrentPosition() async {
    if (_locating) return;
    setState(() {
      _locating = true;
      _error = null;
    });
    try {
      final position = await DeviceLocation.currentPosition();
      if (!position.hasGoodDeliveryAccuracy) {
        throw const DeviceLocationException(
          'La position n’est pas encore assez précise. Réessayez dans quelques instants ou choisissez l’adresse sur la carte.',
        );
      }
      final place = await _geo.reverse(
        latitude: position.latitude,
        longitude: position.longitude,
        fresh: true,
      );
      _applyPlace(place);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _pickOnMap() async {
    final place = await Navigator.of(context).push<GeoResolvedPlace>(
      MaterialPageRoute<GeoResolvedPlace>(
        builder: (_) => AddressMapPickerScreen(
          initialLatitude: _latitude,
          initialLongitude: _longitude,
        ),
      ),
    );
    if (place == null || !mounted) return;
    _applyPlace(place);
  }

  void _applyPlace(GeoResolvedPlace place) {
    _city.text = place.city.trim().isEmpty ? 'Abidjan' : place.city.trim();
    _commune.text = place.commune.trim();
    _quartier.text = place.quartier.trim();
    _fullAddress.text = place.displayName.trim();
    setState(() {
      _latitude = place.latitude;
      _longitude = place.longitude;
      _error = null;
    });
  }

  String _buildPersistedAddress() {
    final parts = <String>[
      _fullAddress.text.trim(),
      if (_subQuarter.text.trim().isNotEmpty) _subQuarter.text.trim(),
      if (_landmark.text.trim().isNotEmpty) 'Repère : ${_landmark.text.trim()}',
    ];
    return parts.where((e) => e.isNotEmpty).join(', ');
  }


  List<AddressTypeOption> get _effectiveTypes {
    final remote =
        OvanieReferenceDataStore.instance.options('address_types');
    if (remote.isNotEmpty) {
      return remote
          .map(
            (item) => AddressTypeOption(code: item.code, label: item.label),
          )
          .toList(growable: false);
    }
    // `widget.types` provient lui aussi de Laravel (payload adresses).
    // On le conserve comme source serveur secondaire, mais plus aucune liste
    // métier n'est codée en dur dans Flutter.
    if (widget.types.isNotEmpty) return widget.types;

    return const <AddressTypeOption>[];
  }

  IconData _addressTypeIcon(String code) => switch (code) {
        'home' => Icons.home_outlined,
        'office' => Icons.apartment_rounded,
        'site' => Icons.construction_rounded,
        'warehouse' => Icons.warehouse_rounded,
        _ => Icons.location_on_outlined,
      };

  Color _addressTypeColor(String code) => switch (code) {
        'home' => const Color(0xFFFF6500),
        'office' => const Color(0xFF6739E6),
        'site' => const Color(0xFF0B56E6),
        'warehouse' => const Color(0xFF079843),
        _ => const Color(0xFF536C98),
      };

  Future<void> _save() async {
    if (_saving || !_formKey.currentState!.validate()) return;
    if (_effectiveTypes.isEmpty) {
      setState(() {
        _error =
            'Les types d’adresse OVANIE sont indisponibles. Rechargez les données puis réessayez.';
      });
      return;
    }

    final phoneDigits = ciLocalPhoneDigits(_contactPhone.text);
    final sessionPhoneDigits = ciLocalPhoneDigits(SessionStore.instance.phone ?? '');
    final effectivePhone = phoneDigits.length == 10
        ? _contactPhone.text
        : (sessionPhoneDigits.length == 10 ? SessionStore.instance.phone ?? '' : '');

    if (effectivePhone.trim().isEmpty) {
      setState(() => _error = 'Renseignez un numéro de téléphone de contact valide.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final draft = ClientAddressDraft(
        type: _type,
        label: _label.text.trim(),
        recipientName: _contactName.text.trim().isEmpty
            ? (SessionStore.instance.name ?? 'Client OVANIE')
            : _contactName.text.trim(),
        city: _city.text.trim(),
        commune: _commune.text.trim(),
        quartier: _quartier.text.trim(),
        address: _buildPersistedAddress(),
        phone: normalizeCiPhoneForApi(effectivePhone),
        latitude: _latitude,
        longitude: _longitude,
        isDefault: _isDefault,
      );

      final saved = widget.existing == null
          ? await _repository.create(draft)
          : await _repository.update(widget.existing!.id, draft);
      if (!mounted) return;
      Navigator.of(context).pop(saved);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final editing = widget.existing != null;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: CustomScrollView(
            physics: const BouncingScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(34, 22, 34, 0),
                  child: _FormHeader(
                    title: editing ? 'Modifier l’adresse' : 'Ajouter une adresse',
                    subtitle: 'Renseignez les informations pour une livraison rapide et précise.',
                    onBack: () => Navigator.maybePop(context),
                  ),
                ),
              ),
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(34, 22, 34, 0),
                  child: _InfoBanner(
                    text: 'L’adresse principale est utilisée par défaut lors de la validation de votre commande.',
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(34, 20, 34, 0),
                  child: Row(
                    children: [
                      Expanded(
                        child: _TopLocationButton(
                          filled: true,
                          icon: Icons.my_location_rounded,
                          label: _locating ? 'Localisation…' : 'Utiliser ma position actuelle',
                          onTap: _locating ? null : _useCurrentPosition,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: _TopLocationButton(
                          filled: false,
                          icon: Icons.map_outlined,
                          label: 'Choisir sur la carte',
                          onTap: _pickOnMap,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(34, 30, 34, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _SectionTitle('Type d’adresse'),
                      const SizedBox(height: 14),
                      LayoutBuilder(
                        builder: (context, constraints) {
                          final types = _effectiveTypes;
                          final width = constraints.maxWidth < 620
                              ? (constraints.maxWidth - 12) / 2
                              : (constraints.maxWidth - 36) / 4;
                          return Wrap(
                            spacing: 12,
                            runSpacing: 12,
                            children: [
                              for (final item in types)
                                SizedBox(
                                  width: width,
                                  child: _TypeButton(
                                    type: item.code,
                                    label: item.label,
                                    icon: _addressTypeIcon(item.code),
                                    color: _addressTypeColor(item.code),
                                    selected: _type == item.code,
                                    onTap: () => setState(() => _type = item.code),
                                  ),
                                ),
                            ],
                          );
                        },
                      ),
                      const SizedBox(height: 28),
                      _LabeledField(
                        label: 'Nom de l’adresse',
                        requiredField: true,
                        controller: _label,
                        hint: 'Ex. Chantier Cocody Riviera 3',
                        validator: (value) => (value ?? '').trim().isEmpty ? 'Renseignez le nom de l’adresse.' : null,
                      ),
                      const SizedBox(height: 34),
                      const _SectionTitle('Localisation'),
                      const SizedBox(height: 22),
                      const _StaticSelectField(label: 'Pays / Région', requiredField: true, value: 'Côte d’Ivoire'),
                      const SizedBox(height: 20),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: _LabeledField(
                              label: 'Ville',
                              requiredField: true,
                              controller: _city,
                              hint: 'Abidjan',
                              suffixIcon: Icons.keyboard_arrow_down_rounded,
                              validator: (value) => (value ?? '').trim().isEmpty ? 'Ville requise.' : null,
                            ),
                          ),
                          const SizedBox(width: 28),
                          Expanded(
                            child: _LabeledField(
                              label: 'Commune',
                              requiredField: true,
                              controller: _commune,
                              hint: 'Cocody',
                              suffixIcon: Icons.keyboard_arrow_down_rounded,
                              validator: (value) => (value ?? '').trim().isEmpty ? 'Commune requise.' : null,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: _LabeledField(
                              label: 'Quartier / Zone',
                              requiredField: true,
                              controller: _quartier,
                              hint: 'Riviera 3',
                              suffixIcon: Icons.keyboard_arrow_down_rounded,
                              validator: (value) => (value ?? '').trim().isEmpty ? 'Quartier requis.' : null,
                            ),
                          ),
                          const SizedBox(width: 28),
                          Expanded(
                            child: _LabeledField(
                              label: 'Sous-quartier / Lot',
                              labelSuffix: '(optionnel)',
                              controller: _subQuarter,
                              hint: 'Ex. Lot 123, Zone A',
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      _LabeledField(
                        label: 'Adresse complète',
                        requiredField: true,
                        controller: _fullAddress,
                        hint: 'Rue des Jardins, près du carrefour, Chantier Cocody Riviera 3.',
                        maxLines: 3,
                        maxLength: 200,
                        validator: (value) => (value ?? '').trim().isEmpty ? 'Adresse complète requise.' : null,
                      ),
                      const SizedBox(height: 20),
                      _LabeledField(
                        label: 'Point de repère',
                        labelSuffix: '(optionnel)',
                        controller: _landmark,
                        hint: 'Ex. Immeuble beige à côté de la station Shell',
                      ),
                      const SizedBox(height: 32),
                      const Text(
                        'Contact sur place ',
                        style: TextStyle(color: Color(0xFF061A56), fontSize: 15, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Personne à contacter pour faciliter la livraison',
                        style: TextStyle(color: Color(0xFF53658F), fontSize: 12.5),
                      ),
                      const SizedBox(height: 14),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: _LabeledField(
                              label: 'Nom complet',
                              compactLabel: true,
                              controller: _contactName,
                              hint: 'Ex. Ali Koné',
                            ),
                          ),
                          const SizedBox(width: 28),
                          Expanded(
                            child: _LabeledField(
                              label: 'Téléphone',
                              compactLabel: true,
                              controller: _contactPhone,
                              hint: '07 00 00 00 00',
                              keyboardType: TextInputType.phone,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),
                      Row(
                        children: [
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Définir comme adresse principale',
                                  style: TextStyle(color: Color(0xFF061A56), fontSize: 14.5, fontWeight: FontWeight.w800),
                                ),
                                SizedBox(height: 4),
                                Text(
                                  'Cette adresse sera utilisée par défaut lors de la validation de votre commande.',
                                  style: TextStyle(color: Color(0xFF53658F), fontSize: 11.7),
                                ),
                              ],
                            ),
                          ),
                          Switch.adaptive(
                            value: _isDefault,
                            activeColor: const Color(0xFF1557E8),
                            onChanged: (value) => setState(() => _isDefault = value),
                          ),
                        ],
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 16),
                        _ErrorBox(message: _error!),
                      ],
                      const SizedBox(height: 26),
                      SizedBox(
                        width: double.infinity,
                        height: 62,
                        child: FilledButton(
                          onPressed: _saving ? null : _save,
                          style: FilledButton.styleFrom(
                            backgroundColor: const Color(0xFF0A50EB),
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          ),
                          child: _saving
                              ? const SizedBox(
                                  width: 23,
                                  height: 23,
                                  child: CircularProgressIndicator(strokeWidth: 2.3, color: Colors.white),
                                )
                              : Text(
                                  editing ? 'Enregistrer les modifications' : 'Enregistrer l’adresse',
                                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                                ),
                        ),
                      ),
                      const SizedBox(height: 26),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FormHeader extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback onBack;

  const _FormHeader({required this.title, required this.subtitle, required this.onBack});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        InkWell(
          onTap: onBack,
          borderRadius: BorderRadius.circular(30),
          child: const Padding(
            padding: EdgeInsets.all(3),
            child: Icon(Icons.arrow_back_rounded, color: Color(0xFF061A56), size: 31),
          ),
        ),
        const SizedBox(width: 20),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: Color(0xFF061A56),
                  fontSize: 29,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 7),
              Text(
                subtitle,
                style: const TextStyle(color: Color(0xFF53658F), fontSize: 13.5),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoBanner extends StatelessWidget {
  final String text;
  const _InfoBanner({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFE),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFD3DEF4)),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline_rounded, color: Color(0xFF0A50EB), size: 24),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(color: Color(0xFF17306A), fontSize: 12.8, height: 1.35),
            ),
          ),
        ],
      ),
    );
  }
}

class _TopLocationButton extends StatelessWidget {
  final bool filled;
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  const _TopLocationButton({required this.filled, required this.icon, required this.label, this.onTap});

  @override
  Widget build(BuildContext context) {
    final child = FittedBox(
      fit: BoxFit.scaleDown,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 23),
          const SizedBox(width: 11),
          Text(label, maxLines: 1, style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800)),
        ],
      ),
    );
    return SizedBox(
      height: 58,
      child: filled
          ? FilledButton(
              onPressed: onTap,
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFFF4C0A),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: child,
            )
          : OutlinedButton(
              onPressed: onTap,
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF0A50EB),
                side: const BorderSide(color: Color(0xFFB7C4E4), width: 1.1),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: child,
            ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(color: Color(0xFF061A56), fontSize: 17, fontWeight: FontWeight.w900),
    );
  }
}

class _TypeButton extends StatelessWidget {
  final String type;
  final String label;
  final IconData icon;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  const _TypeButton({
    required this.type,
    required this.label,
    required this.icon,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 62,
      child: OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          foregroundColor: selected ? const Color(0xFF0A50EB) : const Color(0xFF061A56),
          backgroundColor: Colors.white,
          side: BorderSide(color: selected ? const Color(0xFF0A50EB) : const Color(0xFFD6DDEB), width: selected ? 1.6 : 1),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          padding: const EdgeInsets.symmetric(horizontal: 8),
        ),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, color: color, size: 28),
              const SizedBox(width: 9),
              Text(label, style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ),
    );
  }
}

class _StaticSelectField extends StatelessWidget {
  final String label;
  final bool requiredField;
  final String value;

  const _StaticSelectField({required this.label, required this.requiredField, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _FieldLabel(label: label, requiredField: requiredField),
        const SizedBox(height: 9),
        Container(
          height: 54,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFD5DCEB)),
          ),
          child: Row(
            children: [
              Expanded(child: Text(value, style: const TextStyle(color: Color(0xFF061A56), fontSize: 14.5))),
              const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF061A56)),
            ],
          ),
        ),
      ],
    );
  }
}

class _LabeledField extends StatelessWidget {
  final String label;
  final String? labelSuffix;
  final bool requiredField;
  final bool compactLabel;
  final TextEditingController controller;
  final String? hint;
  final int maxLines;
  final int? maxLength;
  final IconData? suffixIcon;
  final TextInputType? keyboardType;
  final String? Function(String?)? validator;

  const _LabeledField({
    required this.label,
    required this.controller,
    this.labelSuffix,
    this.requiredField = false,
    this.compactLabel = false,
    this.hint,
    this.maxLines = 1,
    this.maxLength,
    this.suffixIcon,
    this.keyboardType,
    this.validator,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _FieldLabel(
          label: label,
          suffix: labelSuffix,
          requiredField: requiredField,
          compact: compactLabel,
        ),
        const SizedBox(height: 9),
        TextFormField(
          controller: controller,
          validator: validator,
          keyboardType: keyboardType,
          maxLines: maxLines,
          maxLength: maxLength,
          style: const TextStyle(color: Color(0xFF061A56), fontSize: 14.2),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: Color(0xFF8A96B0), fontSize: 13.8),
            filled: true,
            fillColor: Colors.white,
            counterStyle: const TextStyle(color: Color(0xFF53658F), fontSize: 10.5),
            suffixIcon: suffixIcon == null ? null : Icon(suffixIcon, color: const Color(0xFF061A56)),
            contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: maxLines > 1 ? 14 : 15),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: Color(0xFFD5DCEB)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: Color(0xFFD5DCEB)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: Color(0xFF0A50EB), width: 1.4),
            ),
          ),
        ),
      ],
    );
  }
}

class _FieldLabel extends StatelessWidget {
  final String label;
  final String? suffix;
  final bool requiredField;
  final bool compact;

  const _FieldLabel({required this.label, this.suffix, this.requiredField = false, this.compact = false});

  @override
  Widget build(BuildContext context) {
    return RichText(
      text: TextSpan(
        style: TextStyle(
          color: const Color(0xFF061A56),
          fontSize: compact ? 11.5 : 13.2,
          fontWeight: FontWeight.w700,
        ),
        children: [
          TextSpan(text: label),
          if (suffix != null) TextSpan(text: ' $suffix', style: const TextStyle(color: Color(0xFF53658F), fontWeight: FontWeight.w500)),
          if (requiredField) const TextSpan(text: ' *', style: TextStyle(color: Color(0xFFFF3B16))),
        ],
      ),
    );
  }
}

class _ErrorBox extends StatelessWidget {
  final String message;
  const _ErrorBox({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFFFC8C8)),
      ),
      child: Text(message, style: const TextStyle(color: Color(0xFFB42318), fontWeight: FontWeight.w700)),
    );
  }
}
