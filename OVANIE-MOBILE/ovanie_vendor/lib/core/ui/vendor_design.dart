import 'package:flutter/material.dart';

import '../../app/theme.dart';

const vendorNavy = Color(0xFF062A62);
const vendorNavyDeep = Color(0xFF031D47);
const vendorBlue = Color(0xFF0B4FC4);
const vendorOrange = Color(0xFFFF6500);
const vendorText = Color(0xFF0A2A63);
const vendorMuted = Color(0xFF5D6A87);
const vendorFieldBorder = Color(0xFFD7DEE8);
const vendorFieldFill = Color(0xFFFFFFFF);
const vendorPage = Color(0xFFF8FAFD);

class VendorPrimaryButton extends StatelessWidget {
  const VendorPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.blue = false,
    this.height = 54,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final bool blue;
  final double height;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      width: double.infinity,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: blue
                ? const [Color(0xFF07296A), Color(0xFF073C8D)]
                : const [Color(0xFFFF7800), Color(0xFFFF5900)],
          ),
          borderRadius: BorderRadius.circular(10),
          boxShadow: [
            BoxShadow(
              color: (blue ? vendorNavy : vendorOrange).withValues(alpha: .12),
              blurRadius: 16,
              offset: const Offset(0, 7),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(10),
            onTap: loading ? null : onPressed,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (loading) ...[
                    const SizedBox.square(
                      dimension: 19,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Expanded(
                    child: Text(
                      label,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  if (!loading) const Icon(Icons.arrow_forward, color: Colors.white),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class VendorBackButton extends StatelessWidget {
  const VendorBackButton({super.key, this.onPressed, this.dark = false});

  final VoidCallback? onPressed;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkResponse(
        onTap: onPressed ?? () => Navigator.maybePop(context),
        radius: 25,
        child: SizedBox(
          width: 48,
          height: 48,
          child: Icon(
            Icons.arrow_back_ios_new_rounded,
            color: dark ? vendorText : Colors.white,
            size: 24,
          ),
        ),
      ),
    );
  }
}

class VendorCircleIcon extends StatelessWidget {
  const VendorCircleIcon({
    super.key,
    required this.icon,
    this.color = vendorBlue,
    this.background = const Color(0xFFF0F5FD),
    this.size = 42,
    this.iconSize = 22,
  });

  final IconData icon;
  final Color color;
  final Color background;
  final double size;
  final double iconSize;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: background, shape: BoxShape.circle),
      child: Icon(icon, color: color, size: iconSize),
    );
  }
}

InputDecoration vendorInputDecoration({
  required String hint,
  IconData? icon,
  Widget? suffix,
  String? label,
  bool dense = false,
}) {
  return InputDecoration(
    labelText: label,
    hintText: hint,
    hintStyle: const TextStyle(color: Color(0xFF8A94A5), fontSize: 15),
    prefixIcon: icon == null
        ? null
        : Padding(
            padding: const EdgeInsets.all(8),
            child: VendorCircleIcon(icon: icon, size: dense ? 34 : 40, iconSize: dense ? 18 : 21),
          ),
    prefixIconConstraints: const BoxConstraints(minWidth: 50),
    suffixIcon: suffix,
    filled: true,
    fillColor: vendorFieldFill,
    contentPadding: EdgeInsets.symmetric(
      horizontal: 16,
      vertical: dense ? 13 : 16,
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: const BorderSide(color: vendorFieldBorder),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: const BorderSide(color: vendorBlue, width: 1.4),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: const BorderSide(color: OvanieColors.danger),
    ),
    focusedErrorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: const BorderSide(color: OvanieColors.danger, width: 1.4),
    ),
  );
}

class VendorErrorBox extends StatelessWidget {
  const VendorErrorBox(this.message, {super.key});
  final String? message;

  @override
  Widget build(BuildContext context) {
    if (message == null || message!.trim().isEmpty) return const SizedBox.shrink();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: OvanieColors.danger.withValues(alpha: .06),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: OvanieColors.danger.withValues(alpha: .18)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, size: 19, color: OvanieColors.danger),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message!,
              style: const TextStyle(
                color: OvanieColors.danger,
                height: 1.35,
                fontSize: 13,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class VendorInfoBox extends StatelessWidget {
  const VendorInfoBox({super.key, required this.child, this.icon = Icons.info_rounded});
  final Widget child;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF3FF),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: const Color(0xFF1570D8), size: 20),
          const SizedBox(width: 9),
          Expanded(child: child),
        ],
      ),
    );
  }
}

class VendorSectionHeading extends StatelessWidget {
  const VendorSectionHeading({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    this.onIconTap,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback? onIconTap;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        GestureDetector(
          onTap: onIconTap,
          child: Icon(icon, color: const Color(0xFF0A3D98), size: 25),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: vendorText,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 2),
                Text(
                  subtitle!,
                  style: const TextStyle(color: vendorMuted, fontSize: 12.5, height: 1.35),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class VendorSheet extends StatelessWidget {
  const VendorSheet({
    super.key,
    required this.child,
    this.radius = 28,
    this.padding = const EdgeInsets.fromLTRB(24, 28, 24, 28),
  });

  final Widget child;
  final double radius;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(radius)),
        boxShadow: const [
          BoxShadow(color: Color(0x16021536), blurRadius: 22, offset: Offset(0, -4)),
        ],
      ),
      child: child,
    );
  }
}
