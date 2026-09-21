import 'package:flutter/material.dart';

import 'offer_collection_screen.dart';

class FlashSaleScreen extends StatelessWidget {
  final String? offer;

  const FlashSaleScreen({super.key, this.offer});

  @override
  Widget build(BuildContext context) {
    return OvanieOfferCollectionScreen(
      kind: OvanieOfferKind.flash,
      initialOffer: offer,
    );
  }
}
