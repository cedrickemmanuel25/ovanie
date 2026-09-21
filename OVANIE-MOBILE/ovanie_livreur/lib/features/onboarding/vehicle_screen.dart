import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../app/theme.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import 'confirmation_screen.dart';
import 'onboarding_data.dart';

class VehicleScreen extends StatefulWidget {
  const VehicleScreen({super.key, required this.data});

  final OnboardingData data;

  @override
  State<VehicleScreen> createState() => _VehicleScreenState();
}

class _VehicleScreenState extends State<VehicleScreen> {
  final _plateController = TextEditingController();
  String? _error;

  @override
  void initState() {
    super.initState();
    _plateController.text = widget.data.plateNumber;
  }

  @override
  void dispose() {
    _plateController.dispose();
    super.dispose();
  }

  Future<File?> _pickImage() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Prendre une photo'),
              onTap: () => Navigator.of(context).pop(ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choisir depuis la galerie'),
              onTap: () => Navigator.of(context).pop(ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return null;
    final file = await ImagePicker().pickImage(
      source: source,
      imageQuality: 88,
      maxWidth: 1800,
      maxHeight: 1800,
    );
    return file == null ? null : File(file.path);
  }

  Future<void> _pickVehiclePhoto() async {
    final file = await _pickImage();
    if (file != null && mounted) setState(() => widget.data.vehiclePhoto = file);
  }

  Future<void> _pickPlatePhoto() async {
    final file = await _pickImage();
    if (file != null && mounted) setState(() => widget.data.platePhoto = file);
  }

  Future<void> _pickVehicleRegistrationDocument() async {
    final file = await _pickImage();
    if (file != null && mounted) {
      setState(() => widget.data.vehicleRegistrationDocument = file);
    }
  }

  Future<void> _addSupportingDocument() async {
    final file = await _pickImage();
    if (file != null && mounted) {
      setState(() => widget.data.supportingDocuments.add(file));
    }
  }

  Widget _photoTile({
    required String label,
    required File? file,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 148,
        decoration: BoxDecoration(
          color: file == null ? OvanieColors.greenLight.withValues(alpha: .5) : Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: file == null ? OvanieColors.green.withValues(alpha: .35) : OvanieColors.border,
            width: file == null ? 1.6 : 1,
          ),
        ),
        child: file == null
            ? Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                      child: const Icon(Icons.add_a_photo_rounded, color: OvanieColors.green, size: 22),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      label,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: OvanieColors.greenDark, fontWeight: FontWeight.w700, fontSize: 12.5),
                    ),
                  ],
                ),
              )
            : Stack(
                fit: StackFit.expand,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(18),
                    child: Image.file(file, fit: BoxFit.cover, width: double.infinity),
                  ),
                  Positioned(
                    top: 8,
                    right: 8,
                    child: Container(
                      width: 26,
                      height: 26,
                      decoration: const BoxDecoration(color: OvanieColors.green, shape: BoxShape.circle),
                      child: const Icon(Icons.check_rounded, color: Colors.white, size: 16),
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  void _continue() {
    widget.data.plateNumber = _plateController.text.trim();
    if (widget.data.vehicleType == null) {
      setState(() => _error = 'Sélectionnez le type de votre véhicule.');
      return;
    }
    if (widget.data.plateNumber.isEmpty) {
      setState(() => _error = 'Renseignez le numéro d’immatriculation de votre véhicule.');
      return;
    }
    if (widget.data.vehiclePhoto == null) {
      setState(() => _error = 'Ajoutez une photo claire du véhicule. OVANIE utilisera cette photo pour identifier le véhicule et détecter sa couleur.');
      return;
    }
    setState(() => _error = null);
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ConfirmationScreen(data: widget.data)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final data = widget.data;
    return Scaffold(
      appBar: AppBar(
        elevation: 0,
        backgroundColor: OvanieColors.background,
        foregroundColor: OvanieColors.text,
        title: const Text('Mon véhicule'),
      ),
      backgroundColor: OvanieColors.background,
      body: SafeArea(
        child: OvanieWizardBody(
          content: [
            const OvanieStepHeader(step: 4, total: 5, title: 'Votre véhicule'),
              const SizedBox(height: 8),
              const Text(
                'Ce véhicule vous appartient (ou appartient à votre entreprise partenaire) : '
                'il ne s’agit pas d’un véhicule fourni par OVANIE.',
                style: TextStyle(color: OvanieColors.muted, fontSize: 13.5, height: 1.4),
              ),
              const SizedBox(height: 20),
              const Text(
                'Type de véhicule',
                style: TextStyle(color: OvanieColors.text, fontWeight: FontWeight.w700, fontSize: 15),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: kVehicleTypes
                    .map(
                      (type) => OvanieChoiceChip(
                        label: type,
                        selected: data.vehicleType == type,
                        onTap: () => setState(() => data.vehicleType = type),
                      ),
                    )
                    .toList(),
              ),
              const SizedBox(height: 20),
              TextFormField(
                controller: _plateController,
                textCapitalization: TextCapitalization.characters,
                decoration: ovanieInputDecoration(
                  hint: 'Numéro d’immatriculation',
                  icon: Icons.confirmation_number_outlined,
                ),
              ),
              const SizedBox(height: 24),
              const Text(
                'Photo du véhicule *',
                style: TextStyle(color: OvanieColors.text, fontWeight: FontWeight.w700, fontSize: 15),
              ),
              const SizedBox(height: 12),
              _photoTile(label: 'Ajouter une photo du véhicule', file: data.vehiclePhoto, onTap: _pickVehiclePhoto),
              const SizedBox(height: 20),
              const Text(
                'Photo de la plaque d’immatriculation',
                style: TextStyle(color: OvanieColors.text, fontWeight: FontWeight.w700, fontSize: 15),
              ),
              const SizedBox(height: 12),
              _photoTile(label: 'Ajouter une photo de la plaque', file: data.platePhoto, onTap: _pickPlatePhoto),
              const SizedBox(height: 20),
              const Text(
                'Carte grise du véhicule (facultatif)',
                style: TextStyle(color: OvanieColors.text, fontWeight: FontWeight.w700, fontSize: 15),
              ),
              const SizedBox(height: 12),
              _photoTile(
                label: 'Ajouter la carte grise',
                file: data.vehicleRegistrationDocument,
                onTap: _pickVehicleRegistrationDocument,
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Justificatifs supplémentaires (facultatif)',
                      style: TextStyle(color: OvanieColors.text, fontWeight: FontWeight.w700, fontSize: 15),
                    ),
                  ),
                  TextButton.icon(
                    onPressed: _addSupportingDocument,
                    icon: const Icon(Icons.add, size: 18),
                    label: const Text('Ajouter'),
                  ),
                ],
              ),
              if (data.supportingDocuments.isNotEmpty)
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    for (var i = 0; i < data.supportingDocuments.length; i++)
                      ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: Image.file(
                          data.supportingDocuments[i],
                          width: 72,
                          height: 72,
                          fit: BoxFit.cover,
                        ),
                      ),
                  ],
                ),
          ],
          footer: [
            if (_error != null) OvanieErrorBox(_error),
            OvaniePrimaryButton(label: 'Continuer', onPressed: _continue),
          ],
        ),
      ),
    );
  }
}
