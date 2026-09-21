import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/layout/responsive.dart';
import '../../features/products/domain/product_model.dart';
import 'product_card.dart';

class ProductHorizontalSection extends StatelessWidget {
  final String title;
  final List<ProductModel> products;
  final EdgeInsetsGeometry? padding;

  const ProductHorizontalSection({
    super.key,
    required this.title,
    required this.products,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) return const SizedBox.shrink();

    return LayoutBuilder(
      builder: (context, constraints) {
        final availableWidth = constraints.maxWidth;
        final side =
            OvanieResponsive.horizontalPaddingForWidth(availableWidth);
        final cardWidth =
            OvanieResponsive.horizontalProductCardWidthForWidth(availableWidth);
        final cardHeight =
            ProductCard.extentForWidth(context, width: cardWidth);

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.fromLTRB(side, 6, side, 10),
              child: Text(
                title,
                style: const TextStyle(
                  color: OvanieColors.text,
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            SizedBox(
              height: cardHeight + 12,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: padding ?? EdgeInsets.fromLTRB(side, 0, side, 12),
                itemCount: products.length,
                separatorBuilder: (_, __) => const SizedBox(width: 10),
                itemBuilder: (context, index) {
                  return SizedBox(
                    width: cardWidth,
                    child: ProductCard(product: products[index]),
                  );
                },
              ),
            ),
          ],
        );
      },
    );
  }
}
