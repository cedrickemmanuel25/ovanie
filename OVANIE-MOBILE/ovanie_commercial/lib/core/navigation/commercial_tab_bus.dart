import 'package:flutter/foundation.dart';

/// Lightweight navigation bridge used by nested Commercial screens.
///
/// Nested flows (Boutiques / Produits) can return to the root screen and ask
/// it to open another main tab without rebuilding services or losing the
/// authenticated API client.
class CommercialTabBus {
  CommercialTabBus._();

  static final ValueNotifier<int> requestedTab = ValueNotifier<int>(-1);

  static void request(int index) {
    requestedTab.value = -1;
    requestedTab.value = index;
  }

  static void clear() {
    if (requestedTab.value != -1) requestedTab.value = -1;
  }
}
