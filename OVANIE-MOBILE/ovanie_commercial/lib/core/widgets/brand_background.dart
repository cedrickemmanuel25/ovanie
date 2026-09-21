import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/ovanie_colors.dart';

class BrandBackground extends StatelessWidget {
  const BrandBackground({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Color(0xFF00102A),
            Color(0xFF031D48),
            Color(0xFF001638),
            Color(0xFF00102B),
          ],
          stops: [0, .32, .68, 1],
        ),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          const Positioned.fill(child: _BlueGlow()),
          Positioned.fill(child: CustomPaint(painter: _WavePainter())),
          child,
        ],
      ),
    );
  }
}

class _BlueGlow extends StatelessWidget {
  const _BlueGlow();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: RadialGradient(
            center: const Alignment(0, -.72),
            radius: .9,
            colors: [
              OvanieColors.blue.withOpacity(.25),
              OvanieColors.navy900.withOpacity(.08),
              Colors.transparent,
            ],
            stops: const [0, .42, 1],
          ),
        ),
      ),
    );
  }
}

class _WavePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final baseY = size.height * .355;
    final wave = Path();
    wave.moveTo(-30, baseY + 22);
    for (double x = -30; x <= size.width + 30; x += 3) {
      final y = baseY + math.sin((x / size.width) * math.pi * 1.55) * 23;
      wave.lineTo(x, y);
    }

    final glow = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 7
      ..color = OvanieColors.blue.withOpacity(.07)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 12);
    canvas.drawPath(wave, glow);

    final line = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = .9
      ..color = const Color(0xFF28A9FF).withOpacity(.58)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 1.5);
    canvas.drawPath(wave, line);

    final highlight = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = .35
      ..color = Colors.white.withOpacity(.38);
    canvas.drawPath(wave, highlight);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
