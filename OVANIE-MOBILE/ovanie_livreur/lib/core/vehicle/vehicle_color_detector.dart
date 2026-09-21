import 'dart:io';
import 'dart:math' as math;
import 'dart:ui' as ui;

/// Détection automatique de la couleur dominante d'un véhicule à partir de
/// la vraie photo fournie lors de l'inscription.
///
/// Aucun champ couleur n'est demandé au livreur. Le résultat est envoyé au
/// backend comme secours lorsque l'environnement PHP ne dispose pas de GD.
/// La photo réelle reste toujours la référence visuelle dans OVANIE.
class VehicleDetectedColor {
  const VehicleDetectedColor({required this.label, required this.hex});

  final String label;
  final String hex;
}

class VehicleColorDetector {
  const VehicleColorDetector._();

  static Future<VehicleDetectedColor?> detect(File? file) async {
    if (file == null || !await file.exists()) return null;

    try {
      final bytes = await file.readAsBytes();
      if (bytes.isEmpty) return null;

      // Une image réduite suffit pour déterminer la teinte dominante et évite
      // de charger une photo 4K entière en mémoire.
      final codec = await ui.instantiateImageCodec(
        bytes,
        targetWidth: 96,
        targetHeight: 96,
      );
      final frame = await codec.getNextFrame();
      final image = frame.image;
      final data = await image.toByteData(format: ui.ImageByteFormat.rawRgba);
      if (data == null) return null;

      final width = image.width;
      final height = image.height;
      final rgba = data.buffer.asUint8List();

      final bins = List.generate(
        12,
        (_) => _ColorBin(),
      );
      final gray = _ColorBin();
      double chromaticWeight = 0;
      double grayWeight = 0;

      // Comme côté Laravel, on privilégie le centre de la photo : la plupart
      // des photos d'inscription cadrent le véhicule dans cette zone.
      final minX = (width * .08).floor();
      final maxX = (width * .92).ceil().clamp(1, width).toInt();
      final minY = (height * .08).floor();
      final maxY = (height * .92).ceil().clamp(1, height).toInt();

      for (var y = minY; y < maxY; y += 2) {
        for (var x = minX; x < maxX; x += 2) {
          final index = (y * width + x) * 4;
          if (index + 3 >= rgba.length) continue;
          final r = rgba[index];
          final g = rgba[index + 1];
          final b = rgba[index + 2];
          final a = rgba[index + 3];
          if (a < 180) continue;

          final hsv = _rgbToHsv(r, g, b);
          final h = hsv.$1;
          final s = hsv.$2;
          final v = hsv.$3;
          if (v < .06 || v > .98) continue;

          if (s >= .20) {
            final binIndex = (h / 30).floor() % 12;
            final weight = math.max(.02, s * (.55 + math.min(v, .85)));
            bins[binIndex].add(r, g, b, weight);
            chromaticWeight += weight;
          } else {
            final weight = .35 + (1 - s);
            gray.add(r, g, b, weight);
            grayWeight += weight;
          }
        }
      }

      _ColorBin? winning;
      for (final bin in bins) {
        if (winning == null || bin.weight > winning.weight) winning = bin;
      }

      if (winning != null &&
          winning.weight > 0 &&
          chromaticWeight >= math.max(6.0, grayWeight * .10)) {
        final r = (winning.r / winning.weight).round().clamp(0, 255).toInt();
        final g = (winning.g / winning.weight).round().clamp(0, 255).toInt();
        final b = (winning.b / winning.weight).round().clamp(0, 255).toInt();
        final hsv = _rgbToHsv(r, g, b);
        return VehicleDetectedColor(
          label: _chromaticLabel(hsv.$1, hsv.$3),
          hex: _hex(r, g, b),
        );
      }

      if (gray.weight > 0) {
        final r = (gray.r / gray.weight).round().clamp(0, 255).toInt();
        final g = (gray.g / gray.weight).round().clamp(0, 255).toInt();
        final b = (gray.b / gray.weight).round().clamp(0, 255).toInt();
        final luma = (.2126 * r + .7152 * g + .0722 * b) / 255;
        final label = luma < .22
            ? 'Noir'
            : luma < .48
                ? 'Gris foncé'
                : luma < .78
                    ? 'Gris / argent'
                    : 'Blanc';
        return VehicleDetectedColor(label: label, hex: _hex(r, g, b));
      }
    } catch (_) {
      // L'inscription ne doit jamais être bloquée par l'analyse de couleur.
      // Laravel peut refaire l'analyse côté serveur si GD est disponible.
    }

    return null;
  }

  static (double, double, double) _rgbToHsv(int r, int g, int b) {
    final rf = r / 255.0;
    final gf = g / 255.0;
    final bf = b / 255.0;
    final maxValue = math.max(rf, math.max(gf, bf));
    final minValue = math.min(rf, math.min(gf, bf));
    final delta = maxValue - minValue;
    var h = 0.0;

    if (delta > 0) {
      if (maxValue == rf) {
        h = 60 * (((gf - bf) / delta) % 6);
      } else if (maxValue == gf) {
        h = 60 * (((bf - rf) / delta) + 2);
      } else {
        h = 60 * (((rf - gf) / delta) + 4);
      }
    }
    if (h < 0) h += 360;
    final s = maxValue <= 0 ? 0.0 : delta / maxValue;
    return (h, s, maxValue);
  }

  static String _chromaticLabel(double hue, double value) {
    var label = switch (hue) {
      < 15 => 'Rouge',
      < 38 => 'Orange',
      < 68 => 'Jaune',
      < 165 => 'Vert',
      < 195 => 'Turquoise',
      < 255 => 'Bleu',
      < 290 => 'Violet',
      < 345 => 'Rose / bordeaux',
      _ => 'Rouge',
    };

    if (value < .34 && !label.contains('bordeaux')) {
      label = '$label foncé';
    } else if (value > .82 && const ['Bleu', 'Vert', 'Turquoise'].contains(label)) {
      label = '$label clair';
    }
    return label;
  }

  static String _hex(int r, int g, int b) =>
      '#${r.toRadixString(16).padLeft(2, '0')}${g.toRadixString(16).padLeft(2, '0')}${b.toRadixString(16).padLeft(2, '0')}'.toUpperCase();
}

class _ColorBin {
  double weight = 0;
  double r = 0;
  double g = 0;
  double b = 0;

  void add(int red, int green, int blue, double w) {
    weight += w;
    r += red * w;
    g += green * w;
    b += blue * w;
  }
}
