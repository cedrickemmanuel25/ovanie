import 'package:flutter/material.dart';

import 'offer_collection_screen.dart';

class BlackFridayScreen extends StatelessWidget {
  const BlackFridayScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const OvanieOfferCollectionScreen(
      kind: OvanieOfferKind.blackFriday,
      initialOffer: 'black-friday',
    );
  }
}
