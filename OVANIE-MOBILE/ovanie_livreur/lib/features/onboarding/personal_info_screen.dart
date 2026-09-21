import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../app/theme.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import 'onboarding_data.dart';
import 'zones_screen.dart';

class PersonalInfoScreen extends StatefulWidget {
  const PersonalInfoScreen({super.key, required this.data});

  final OnboardingData data;

  @override
  State<PersonalInfoScreen> createState() => _PersonalInfoScreenState();
}

class _PersonalInfoScreenState extends State<PersonalInfoScreen> {
  Future<void> _pickPhoto() async {
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
    if (source == null) return;
    final file = await ImagePicker().pickImage(
      source: source,
      imageQuality: 88,
      maxWidth: 1600,
      maxHeight: 1600,
    );
    if (file != null && mounted) {
      setState(() => widget.data.profilePhoto = File(file.path));
    }
  }

  void _continue() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => ZonesScreen(data: widget.data)));
  }

  @override
  Widget build(BuildContext context) {
    final data = widget.data;
    return OvanieHeroScaffold(
      heroHeightFactor: 0.24,
      showBack: true,
      appBarTitle: 'Créer mon compte livreur',
      child: OvanieWizardBody(
        content: [
          const OvanieStepHeader(
            step: 1,
            total: 5,
            title: 'Informations personnelles',
          ),
          const SizedBox(height: 16),
          OvanieInfoBox(
            icon: Icons.info_rounded,
            color: OvanieColors.greenDark,
            background: OvanieColors.greenLight,
            child: const Text.rich(
              TextSpan(
                style: TextStyle(
                  color: OvanieColors.text,
                  height: 1.4,
                  fontSize: 13.5,
                ),
                children: [
                  TextSpan(
                    text: 'Ces informations ',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                  TextSpan(
                    text:
                        'ont déjà été enregistrées par OVANIE Logistics et ne peuvent pas être modifiées ici.',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          OvanieReadOnlyField(label: 'Prénom', value: data.firstName),
          const SizedBox(height: 14),
          OvanieReadOnlyField(label: 'Nom', value: data.lastName),
          const SizedBox(height: 14),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Téléphone',
                style: TextStyle(
                  color: OvanieColors.text,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              OvaniePhoneField(readOnly: true, value: data.phone),
            ],
          ),
          const SizedBox(height: 28),
          const Text(
            'Photo de profil / pièce d’identité',
            style: TextStyle(
              color: OvanieColors.text,
              fontWeight: FontWeight.w800,
              fontSize: 15.5,
            ),
          ),
          const SizedBox(height: 12),
          GestureDetector(
            onTap: _pickPhoto,
            child: Container(
              height: 170,
              decoration: BoxDecoration(
                color: data.profilePhoto == null
                    ? OvanieColors.greenLight.withValues(alpha: .5)
                    : Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: data.profilePhoto == null
                      ? OvanieColors.green.withValues(alpha: .35)
                      : OvanieColors.border,
                  width: data.profilePhoto == null ? 1.6 : 1,
                ),
              ),
              child: data.profilePhoto == null
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 52,
                            height: 52,
                            decoration: const BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.add_a_photo_rounded,
                              color: OvanieColors.green,
                              size: 24,
                            ),
                          ),
                          const SizedBox(height: 10),
                          const Text(
                            'Ajouter une photo',
                            style: TextStyle(
                              color: OvanieColors.greenDark,
                              fontWeight: FontWeight.w700,
                              fontSize: 13.5,
                            ),
                          ),
                        ],
                      ),
                    )
                  : Stack(
                      fit: StackFit.expand,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(18),
                          child: Image.file(
                            data.profilePhoto!,
                            fit: BoxFit.cover,
                            width: double.infinity,
                          ),
                        ),
                        Positioned(
                          top: 10,
                          right: 10,
                          child: Container(
                            width: 28,
                            height: 28,
                            decoration: const BoxDecoration(
                              color: OvanieColors.green,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.check_rounded,
                              color: Colors.white,
                              size: 18,
                            ),
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ],
        footer: [OvaniePrimaryButton(label: 'Continuer', onPressed: _continue)],
      ),
    );
  }
}
