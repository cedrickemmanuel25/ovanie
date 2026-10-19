import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'mk_dialogs.dart';
import 'vendor_mockup_ui.dart';

/// Fenêtres de l'app qui précèdent toujours l'appareil photo, la galerie ou
/// l'explorateur de fichiers du téléphone : le vendeur sait ce qu'on lui
/// demande et choisit d'où vient le fichier avant de quitter l'application.

/// Propose les sources possibles et renvoie 'camera', 'gallery' ou 'file'.
Future<String?> showMkSourceSheet(
  BuildContext context, {
  required String title,
  String? message,
  bool camera = true,
  bool gallery = true,
  String? fileLabel,
  IconData fileIcon = Icons.picture_as_pdf_outlined,
  String? art,
  String galleryLabel = 'Choisir dans la galerie',
}) {
  FocusManager.instance.primaryFocus?.unfocus();
  // Le premier choix proposé est le bouton plein ; les suivants sont à contour.
  final options = <(String, String, IconData)>[
    if (camera) ('camera', 'Prendre une photo', Icons.photo_camera_outlined),
    if (gallery) ('gallery', galleryLabel, Icons.image_outlined),
    if (fileLabel != null) ('file', fileLabel, fileIcon),
  ];
  return showMkSheet<String>(
    context,
    builder: (sheet) => MkSheetFrame(
      children: [
        if (art != null) ...[
          ExcludeSemantics(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 170),
                child: Image.asset('assets/images/mockups/$art.jpg', fit: BoxFit.contain),
              ),
            ),
          ),
          const SizedBox(height: 12),
        ] else ...[
          Center(
            child: MkIconDisc(
              camera || gallery ? Icons.add_photo_alternate_outlined : Icons.upload_file_outlined,
              color: Mk.blue,
            ),
          ),
          const SizedBox(height: 12),
        ],
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Mk.navy, fontSize: 21, height: 1.2, fontWeight: FontWeight.w800),
        ),
        if (message != null) ...[
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
          ),
        ],
        const SizedBox(height: 16),
        for (var i = 0; i < options.length; i++) ...[
          if (i > 0) const SizedBox(height: 8),
          MkBarButton(
            label: options[i].$2,
            icon: options[i].$3,
            filled: i == 0,
            color: i == 2 ? Mk.navy : Mk.orange,
            onTap: () => Navigator.pop(sheet, options[i].$1),
          ),
        ],
        const SizedBox(height: 4),
        TextButton(
          onPressed: () => Navigator.pop(sheet),
          style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44)),
          child: const Text(
            'Annuler',
            style: TextStyle(fontFamily: Mk.font, color: Mk.icon, fontSize: 14.5, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
  );
}

/// Choix d'un seul fichier : fenêtre de l'app, puis appareil photo, galerie
/// ou explorateur selon la réponse. Renvoie null si le vendeur renonce.
Future<File?> pickMkFile(
  BuildContext context, {
  required String title,
  String? message,
  bool camera = true,
  bool gallery = true,
  List<String> extensions = const [],
  String? fileLabel,
  IconData fileIcon = Icons.picture_as_pdf_outlined,
  String? art,
  double maxWidth = 2200,
  int quality = 88,
}) async {
  final source = await showMkSourceSheet(
    context,
    title: title,
    message: message,
    camera: camera,
    gallery: gallery,
    fileLabel: extensions.isEmpty ? null : (fileLabel ?? 'Choisir un fichier PDF'),
    fileIcon: fileIcon,
    art: art,
  );
  if (source == null) return null;
  if (source == 'file') {
    final result = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: extensions);
    final path = result?.files.single.path;
    return path == null ? null : File(path);
  }
  final picked = await ImagePicker().pickImage(
    source: source == 'camera' ? ImageSource.camera : ImageSource.gallery,
    maxWidth: maxWidth,
    maxHeight: maxWidth,
    imageQuality: quality,
  );
  return picked == null ? null : File(picked.path);
}

/// Avant d'ouvrir l'explorateur du téléphone pour enregistrer un fichier :
/// rappelle son nom et ce qui va se passer. Renvoie vrai si le vendeur confirme.
Future<bool> confirmMkSave(BuildContext context, {required String fileName, String title = 'Enregistrer ce fichier ?'}) {
  final lower = fileName.toLowerCase();
  final icon = lower.endsWith('.pdf')
      ? Icons.picture_as_pdf_outlined
      : lower.endsWith('.csv')
      ? Icons.table_chart_outlined
      : RegExp(r'\.(jpe?g|png|webp)$').hasMatch(lower)
      ? Icons.image_outlined
      : Icons.insert_drive_file_outlined;
  return showMkConfirmSheet(
    context,
    icon: Icons.file_download_outlined,
    iconColor: Mk.blue,
    title: title,
    equalButtons: true,
    summary: MkSheetSummary(
      child: Row(
        children: [
          Icon(icon, color: lower.endsWith('.pdf') ? const Color(0xFFD92D20) : Mk.icon, size: 24),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              fileName,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    ),
    message: 'Votre téléphone va vous demander dans quel dossier enregistrer ce fichier.',
    confirmLabel: 'Enregistrer',
    confirmIcon: Icons.file_download_outlined,
  );
}
