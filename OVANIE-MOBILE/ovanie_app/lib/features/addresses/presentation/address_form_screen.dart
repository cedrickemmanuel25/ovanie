import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/platform/device_location.dart';
import '../../../core/reference_data/reference_data_store.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../checkout/data/abidjan_localities.dart';
import '../../checkout/data/geo_repository.dart';
import '../../checkout/presentation/checkout_screen.dart' show showLocationPickerSheet;
import '../data/addresses_repository.dart';
import '../domain/client_address.dart';
import 'address_map_picker_screen.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF5E6B80);
const _line = Color(0xFFE7ECF2);
const _fieldBorder = Color(0xFFD5DCE6);
const _pageBackground = Color(0xFFF6F8FB);

const _abidjan = 'Abidjan';
const _otherCity = 'Autre ville';
const _otherQuarter = 'Autre quartier / zone';
const _landmarkPrefix = 'Repère : ';

class AddressFormScreen extends StatefulWidget {
  final ClientAddress? existing;
  final List<AddressTypeOption> types;
  final bool startWithCurrentPosition;

  const AddressFormScreen({super.key, this.existing, this.types = const [], this.startWithCurrentPosition = false});

  @override
  State<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends State<AddressFormScreen> {
  final _repository = const AddressesRepository();
  final _geo = const GeoRepository();

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
  bool _otherCityMode = false;
  bool _otherQuarterMode = false;
  String? _locationWarning;
  String? _saveError;
  final Map<String, String> _errors = {};

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    final existingType = existing?.type ?? 'home';
    _type = const {'home', 'office', 'site', 'warehouse', 'other'}.contains(existingType) ? existingType : 'home';

    // L'adresse enregistrée contient « adresse, sous-quartier, Repère : … » :
    // on redistribue chaque partie dans son champ pour éviter les doublons.
    var storedAddress = existing?.address.trim() ?? '';
    var landmark = '';
    final landmarkIndex = storedAddress.indexOf(_landmarkPrefix);
    if (landmarkIndex >= 0) {
      landmark = storedAddress.substring(landmarkIndex + _landmarkPrefix.length).trim();
      storedAddress = storedAddress.substring(0, landmarkIndex).trim();
    }
    final subQuarter = existing?.sousQuartier.trim() ?? '';
    storedAddress = storedAddress.replaceAll(RegExp(r',\s*$'), '').trim();
    if (subQuarter.isNotEmpty && storedAddress.endsWith(', $subQuarter')) {
      storedAddress = storedAddress.substring(0, storedAddress.length - subQuarter.length - 2).trim();
    }

    final city = existing?.city.trim().isNotEmpty == true ? existing!.city.trim() : _abidjan;
    _otherCityMode = city.toLowerCase() != _abidjan.toLowerCase();
    _label = TextEditingController(text: existing?.label ?? '');
    _city = TextEditingController(text: city);
    _commune = TextEditingController(text: existing?.commune ?? '');
    _quartier = TextEditingController(
      text: existing?.quartierPrincipal.isNotEmpty == true ? existing!.quartierPrincipal : (existing?.quartier ?? ''),
    );
    _subQuarter = TextEditingController(text: subQuarter);
    _fullAddress = TextEditingController(text: storedAddress);
    _landmark = TextEditingController(text: landmark);
    _contactName = TextEditingController(
      text: existing?.recipientName.isNotEmpty == true ? existing!.recipientName : (SessionStore.instance.name ?? ''),
    );
    _contactPhone = TextEditingController(
      text: formatCiPhoneDisplay(
        existing?.phone.isNotEmpty == true ? existing!.phone : (SessionStore.instance.phone ?? ''),
        includeCountryCode: false,
      ),
    );
    _latitude = existing?.latitude;
    _longitude = existing?.longitude;
    _isDefault = existing?.isDefault ?? false;
    _otherQuarterMode = _quartier.text.isNotEmpty && !_quartersFor(_commune.text).contains(_quartier.text);

    if (widget.startWithCurrentPosition && existing == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _useCurrentPosition());
    }
  }

  @override
  void dispose() {
    for (final controller in [
      _label,
      _city,
      _commune,
      _quartier,
      _subQuarter,
      _fullAddress,
      _landmark,
      _contactName,
      _contactPhone,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  List<String> get _communes {
    final loaded = OvanieReferenceDataStore.instance.communeNames;
    return loaded.isNotEmpty ? loaded : kAbidjanQuartiersByCommune.keys.toList(growable: false);
  }

  List<String> _quartersFor(String commune) {
    final loaded = OvanieReferenceDataStore.instance.quartersByCommuneName;
    final source = loaded.isNotEmpty ? loaded : kAbidjanQuartiersByCommune;
    for (final entry in source.entries) {
      if (entry.key.toLowerCase() == commune.trim().toLowerCase()) return entry.value;
    }
    return const [];
  }

  List<AddressTypeOption> get _effectiveTypes {
    final remote = OvanieReferenceDataStore.instance.options('address_types');
    if (remote.isNotEmpty) {
      return remote.map((item) => AddressTypeOption(code: item.code, label: item.label)).toList(growable: false);
    }
    // `widget.types` provient lui aussi de Laravel (payload adresses).
    return widget.types;
  }

  IconData _typeIcon(String code) => switch (code) {
    'home' => Icons.home_outlined,
    'office' => Icons.business_rounded,
    'site' => Icons.apartment_rounded,
    'warehouse' => Icons.warehouse_outlined,
    _ => Icons.location_on_outlined,
  };

  void _clearError(String key) {
    if (_errors.containsKey(key)) setState(() => _errors.remove(key));
  }

  Future<void> _useCurrentPosition() async {
    if (_locating) return;
    setState(() {
      _locating = true;
      _locationWarning = null;
    });
    try {
      final position = await DeviceLocation.currentPosition();
      if (!position.hasGoodDeliveryAccuracy) {
        throw const DeviceLocationException(
          'La position n’est pas encore assez précise. Réessayez dans quelques instants ou choisissez l’adresse sur '
          'la carte.',
        );
      }
      final place = await _geo.reverse(latitude: position.latitude, longitude: position.longitude, fresh: true);
      _applyPlace(place);
    } catch (error) {
      if (mounted) setState(() => _locationWarning = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _pickOnMap() async {
    final place = await Navigator.of(context).push<GeoResolvedPlace>(
      MaterialPageRoute<GeoResolvedPlace>(
        builder: (_) => AddressMapPickerScreen(initialLatitude: _latitude, initialLongitude: _longitude),
      ),
    );
    if (place == null || !mounted) return;
    _applyPlace(place);
  }

  void _applyPlace(GeoResolvedPlace place) {
    final city = place.city.trim().isEmpty ? _abidjan : place.city.trim();
    _city.text = city;
    _commune.text = place.commune.trim();
    _quartier.text = place.quartier.trim();
    _fullAddress.text = place.displayName.trim();
    setState(() {
      _otherCityMode = city.toLowerCase() != _abidjan.toLowerCase();
      _otherQuarterMode = _quartier.text.isNotEmpty && !_quartersFor(_commune.text).contains(_quartier.text);
      _latitude = place.latitude;
      _longitude = place.longitude;
      _locationWarning = null;
      _errors.removeWhere((key, _) => const {'city', 'commune', 'quartier', 'address'}.contains(key));
    });
  }

  Future<void> _pickCity() async {
    FocusManager.instance.primaryFocus?.unfocus();
    final picked = await showLocationPickerSheet(
      context,
      title: 'Ville',
      items: const [_abidjan, _otherCity],
      selected: _otherCityMode ? _otherCity : _abidjan,
    );
    if (picked == null || !mounted) return;
    setState(() {
      final other = picked == _otherCity;
      if (other != _otherCityMode) {
        _commune.clear();
        _quartier.clear();
        _otherQuarterMode = false;
      }
      _otherCityMode = other;
      _city.text = other ? '' : _abidjan;
      _errors.remove('city');
    });
  }

  Future<void> _pickCommune() async {
    FocusManager.instance.primaryFocus?.unfocus();
    final picked = await showLocationPickerSheet(
      context,
      title: 'Commune',
      items: _communes,
      selected: _commune.text.isEmpty ? null : _commune.text,
    );
    if (picked == null || !mounted) return;
    setState(() {
      if (picked != _commune.text) {
        _quartier.clear();
        _otherQuarterMode = false;
      }
      _commune.text = picked;
      _errors.remove('commune');
    });
  }

  Future<void> _pickQuarter() async {
    if (_commune.text.trim().isEmpty) {
      setState(() => _errors['commune'] = 'Choisissez d’abord la commune.');
      return;
    }
    FocusManager.instance.primaryFocus?.unfocus();
    final picked = await showLocationPickerSheet(
      context,
      title: 'Quartier',
      items: [..._quartersFor(_commune.text), _otherQuarter],
      selected: _otherQuarterMode ? _otherQuarter : (_quartier.text.isEmpty ? null : _quartier.text),
    );
    if (picked == null || !mounted) return;
    setState(() {
      _otherQuarterMode = picked == _otherQuarter;
      _quartier.text = _otherQuarterMode ? '' : picked;
      _errors.remove('quartier');
    });
  }

  bool _validate() {
    final errors = <String, String>{};
    if (_label.text.trim().isEmpty) errors['label'] = 'Renseignez le nom de l’adresse.';
    if (_city.text.trim().isEmpty) errors['city'] = 'Ville requise.';
    if (_commune.text.trim().isEmpty) errors['commune'] = 'Commune requise.';
    if (_quartier.text.trim().isEmpty) errors['quartier'] = 'Quartier requis.';
    if (_fullAddress.text.trim().isEmpty) errors['address'] = 'Adresse complète requise.';
    if (ciLocalPhoneDigits(_contactPhone.text).length != 10) {
      errors['phone'] = 'Renseignez un numéro de téléphone de contact valide.';
    }
    setState(() {
      _errors
        ..clear()
        ..addAll(errors);
    });
    return errors.isEmpty;
  }

  String _persistedAddress() {
    final parts = <String>[
      _fullAddress.text.trim(),
      if (_subQuarter.text.trim().isNotEmpty) _subQuarter.text.trim(),
      if (_landmark.text.trim().isNotEmpty) '$_landmarkPrefix${_landmark.text.trim()}',
    ];
    return parts.where((e) => e.isNotEmpty).join(', ');
  }

  Future<void> _save() async {
    if (_saving) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() => _saveError = null);
    if (!_validate()) return;
    if (_effectiveTypes.isEmpty) {
      setState(() => _saveError = 'Les types d’adresse OVANIE sont indisponibles. Rechargez puis réessayez.');
      return;
    }
    setState(() => _saving = true);
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
        address: _persistedAddress(),
        phone: normalizeCiPhoneForApi(_contactPhone.text),
        latitude: _latitude,
        longitude: _longitude,
        isDefault: _isDefault,
      );
      final saved = widget.existing == null
          ? await _repository.create(draft)
          : await _repository.update(widget.existing!.id, draft);
      if (mounted) Navigator.of(context).pop(saved);
    } catch (error) {
      if (mounted) setState(() => _saveError = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final editing = widget.existing != null;
    final types = _effectiveTypes;
    return Scaffold(
      backgroundColor: _pageBackground,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
        ),
        title: Text(
          editing ? 'Modifier l’adresse' : 'Ajouter une adresse',
          style: const TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(62),
          child: Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(14, 2, 14, 12),
            child: Row(
              children: [
                Expanded(
                  child: _TopButton(
                    icon: Icons.gps_fixed_rounded,
                    label: _locating ? 'Localisation…' : 'Ma position',
                    loading: _locating,
                    onTap: _locating ? null : _useCurrentPosition,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _TopButton(icon: Icons.map_outlined, label: 'Sur la carte', onTap: _pickOnMap),
                ),
              ],
            ),
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          color: Colors.white,
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
          child: SizedBox(
            height: 50,
            child: FilledButton(
              onPressed: _saving ? () {} : _save,
              style: FilledButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: _saving
                  ? const SizedBox.square(
                      dimension: 22,
                      child: CircularProgressIndicator(strokeWidth: 2.3, color: Colors.white),
                    )
                  : Text(
                      editing ? 'Enregistrer les modifications' : 'Enregistrer l’adresse',
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                    ),
            ),
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(10, 10, 10, 20),
        children: [
          if (_locationWarning != null) ...[
            Container(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF7E0),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFF6D98B)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 24,
                    height: 24,
                    decoration: const BoxDecoration(color: Color(0xFFF2A60C), shape: BoxShape.circle),
                    child: const Icon(Icons.priority_high_rounded, color: Colors.white, size: 16),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      _locationWarning!,
                      style: const TextStyle(color: Color(0xFF6B4A06), fontSize: 13, height: 1.4),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
          ],
          _Section(
            title: 'Type d’adresse',
            children: [
              SizedBox(
                height: 40,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: types.length,
                  separatorBuilder: (_, _) => const SizedBox(width: 8),
                  itemBuilder: (context, index) {
                    final item = types[index];
                    return _TypeChip(
                      label: item.label,
                      icon: _typeIcon(item.code),
                      selected: _type == item.code,
                      onTap: () => setState(() => _type = item.code),
                    );
                  },
                ),
              ),
              _Field(
                label: 'Nom de l’adresse',
                controller: _label,
                hint: 'Ex. Chantier Cocody Riviera 3',
                error: _errors['label'],
                onChanged: () => _clearError('label'),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _Section(
            title: 'Localisation',
            children: [
              _Picker(label: 'Ville', value: _otherCityMode ? _otherCity : _abidjan, onTap: _pickCity),
              if (_otherCityMode) ...[
                _Field(
                  label: 'Nom de la ville',
                  controller: _city,
                  hint: 'Ex. Bouaké',
                  error: _errors['city'],
                  onChanged: () => _clearError('city'),
                ),
                _Field(
                  label: 'Commune',
                  controller: _commune,
                  hint: 'Ex. Koko',
                  error: _errors['commune'],
                  onChanged: () => _clearError('commune'),
                ),
                _Field(
                  label: 'Quartier / Zone',
                  controller: _quartier,
                  hint: 'Ex. Quartier Commerce',
                  error: _errors['quartier'],
                  onChanged: () => _clearError('quartier'),
                ),
              ] else ...[
                _Picker(
                  label: 'Commune',
                  value: _commune.text,
                  hint: 'Choisir la commune',
                  error: _errors['commune'],
                  onTap: _pickCommune,
                ),
                _Picker(
                  label: 'Quartier / Zone',
                  value: _otherQuarterMode ? _otherQuarter : _quartier.text,
                  hint: _commune.text.isEmpty ? 'Choisissez d’abord la commune' : 'Choisir le quartier',
                  error: _otherQuarterMode ? null : _errors['quartier'],
                  onTap: _pickQuarter,
                ),
                if (_otherQuarterMode)
                  _Field(
                    label: 'Nom du quartier / de la zone',
                    controller: _quartier,
                    hint: 'Ex. Cité des Arts',
                    error: _errors['quartier'],
                    onChanged: () => _clearError('quartier'),
                  ),
              ],
              _Field(label: 'Sous-quartier / Lot', controller: _subQuarter, hint: 'Ex. Lot 123, Zone A'),
              _Field(
                label: 'Adresse complète',
                controller: _fullAddress,
                hint: 'Ex. Rue des Jardins, près du carrefour, Chantier Cocody Riviera 3.',
                maxLines: 3,
                maxLength: 200,
                error: _errors['address'],
                onChanged: () => _clearError('address'),
              ),
              _Field(
                label: 'Point de repère',
                controller: _landmark,
                hint: 'Ex. Immeuble beige à côté de la station Shell',
              ),
            ],
          ),
          const SizedBox(height: 10),
          _Section(
            title: 'Contact sur place',
            children: [
              _Field(label: 'Nom complet', controller: _contactName, hint: 'Ex. Ali Koné'),
              _PhoneField(controller: _contactPhone, error: _errors['phone'], onChanged: () => _clearError('phone')),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.fromLTRB(14, 6, 8, 6),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _line),
            ),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Adresse principale',
                    style: TextStyle(color: _navy, fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                ),
                Switch(
                  value: _isDefault,
                  onChanged: (value) => setState(() => _isDefault = value),
                  activeThumbColor: Colors.white,
                  activeTrackColor: OvanieColors.orange,
                  inactiveThumbColor: Colors.white,
                  inactiveTrackColor: const Color(0xFFDDE2E9),
                  trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
                ),
              ],
            ),
          ),
          if (_saveError != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF1F1),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFF7C9C9)),
              ),
              child: Text(_saveError!, style: const TextStyle(color: OvanieColors.danger, fontSize: 13.5)),
            ),
          ],
        ],
      ),
    );
  }
}

class _TopButton extends StatelessWidget {
  const _TopButton({required this.icon, required this.label, required this.onTap, this.loading = false});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 46,
      child: OutlinedButton(
        onPressed: onTap ?? (loading ? () {} : null),
        style: OutlinedButton.styleFrom(
          foregroundColor: _navy,
          side: const BorderSide(color: _navy, width: 1.3),
          padding: const EdgeInsets.symmetric(horizontal: 8),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
        ),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (loading)
                const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2.2, color: _navy))
              else
                Icon(icon, size: 22),
              const SizedBox(width: 10),
              Text(label, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            title,
            style: const TextStyle(color: _navy, fontSize: 17, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          ...children,
        ],
      ),
    );
  }
}

class _TypeChip extends StatelessWidget {
  const _TypeChip({required this.label, required this.icon, required this.selected, required this.onTap});

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? OvanieColors.orange : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: selected ? OvanieColors.orange : _fieldBorder),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 20, color: selected ? Colors.white : const Color(0xFF3B4A60)),
              const SizedBox(width: 7),
              Text(
                label,
                style: TextStyle(color: selected ? Colors.white : _navy, fontSize: 13.5, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Label extends StatelessWidget {
  const _Label(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 12, bottom: 6),
    child: Text(text, style: const TextStyle(color: _muted, fontSize: 13.5)),
  );
}

class _ErrorText extends StatelessWidget {
  const _ErrorText(this.text);

  final String? text;

  @override
  Widget build(BuildContext context) {
    if (text == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 5),
      child: Text(text!, style: const TextStyle(color: OvanieColors.danger, fontSize: 13)),
    );
  }
}

OutlineInputBorder _border(Color color, [double width = 1.1]) => OutlineInputBorder(
  borderRadius: BorderRadius.circular(9),
  borderSide: BorderSide(color: color, width: width),
);

class _Field extends StatelessWidget {
  const _Field({
    required this.label,
    required this.controller,
    this.hint,
    this.error,
    this.onChanged,
    this.maxLines = 1,
    this.maxLength,
  });

  final String label;
  final TextEditingController controller;
  final String? hint;
  final String? error;
  final VoidCallback? onChanged;
  final int maxLines;
  final int? maxLength;

  @override
  Widget build(BuildContext context) {
    final hasError = error != null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Label(label),
        TextField(
          controller: controller,
          minLines: maxLines > 1 ? 2 : 1,
          maxLines: maxLines,
          maxLength: maxLength,
          textCapitalization: TextCapitalization.sentences,
          onChanged: (_) => onChanged?.call(),
          style: const TextStyle(color: _navy, fontSize: 15),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: Color(0xFF9AA5B5), fontSize: 14.5),
            isDense: true,
            filled: true,
            fillColor: Colors.white,
            counterText: '',
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            enabledBorder: _border(hasError ? OvanieColors.danger : _fieldBorder),
            focusedBorder: _border(hasError ? OvanieColors.danger : _navy, 1.3),
          ),
        ),
        _ErrorText(error),
      ],
    );
  }
}

class _Picker extends StatelessWidget {
  const _Picker({required this.label, required this.value, required this.onTap, this.hint = '', this.error});

  final String label;
  final String value;
  final String hint;
  final String? error;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Label(label),
        Material(
          color: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(9),
            side: BorderSide(color: error != null ? OvanieColors.danger : _fieldBorder, width: 1.1),
          ),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(9),
            child: SizedBox(
              height: 48,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 0, 10, 0),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        value.isEmpty ? hint : value,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: value.isEmpty ? const Color(0xFF9AA5B5) : _navy,
                          fontSize: value.isEmpty ? 14.5 : 15,
                        ),
                      ),
                    ),
                    const Icon(Icons.keyboard_arrow_down_rounded, color: _navy, size: 24),
                  ],
                ),
              ),
            ),
          ),
        ),
        _ErrorText(error),
      ],
    );
  }
}

class _PhoneField extends StatelessWidget {
  const _PhoneField({required this.controller, this.error, this.onChanged});

  final TextEditingController controller;
  final String? error;
  final VoidCallback? onChanged;

  @override
  Widget build(BuildContext context) {
    final hasError = error != null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const _Label('Téléphone'),
        Container(
          height: 48,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: hasError ? OvanieColors.danger : _fieldBorder, width: 1.1),
          ),
          child: Row(
            children: [
              // Les adresses de livraison OVANIE sont en Côte d'Ivoire : indicatif fixe.
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 14),
                child: Text('+225', style: TextStyle(color: _navy, fontSize: 15)),
              ),
              Container(width: 1, height: 28, color: _fieldBorder),
              Expanded(
                child: TextField(
                  controller: controller,
                  keyboardType: TextInputType.phone,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9 ]')),
                    LengthLimitingTextInputFormatter(14),
                  ],
                  onChanged: (_) => onChanged?.call(),
                  style: const TextStyle(color: _navy, fontSize: 15),
                  decoration: const InputDecoration(
                    hintText: '07 08 12 34 56',
                    hintStyle: TextStyle(color: Color(0xFF9AA5B5), fontSize: 14.5),
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    filled: false,
                    isDense: true,
                    contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                  ),
                ),
              ),
            ],
          ),
        ),
        _ErrorText(error),
      ],
    );
  }
}
