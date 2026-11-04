import 'dart:async';
import 'package:flutter/widgets.dart';

/// Keeps app data fresh by silently re-fetching on a fixed interval and
/// whenever the app returns to the foreground.
///
/// The shell owns the data lifecycle, so it mixes this in, starts polling in
/// [initState] and implements [onAutoRefresh] with silent provider refreshes.
mixin AutoRefreshMixin<T extends StatefulWidget> on State<T> {
  /// How often background data is refreshed (seconds).
  static const Duration refreshInterval = Duration(seconds: 30);

  Timer? _autoRefreshTimer;
  _LifecycleObserver? _lifecycleObserver;

  /// Start periodic polling and observe app lifecycle. Call from [initState].
  void startAutoRefresh() {
    _lifecycleObserver = _LifecycleObserver(onAutoRefresh);
    WidgetsBinding.instance.addObserver(_lifecycleObserver!);
    _autoRefreshTimer?.cancel();
    _autoRefreshTimer = Timer.periodic(refreshInterval, (_) => onAutoRefresh());
  }

  @override
  void dispose() {
    _autoRefreshTimer?.cancel();
    _autoRefreshTimer = null;
    final observer = _lifecycleObserver;
    if (observer != null) WidgetsBinding.instance.removeObserver(observer);
    _lifecycleObserver = null;
    super.dispose();
  }

  /// Silently re-fetch the data owned by this shell.
  void onAutoRefresh();
}

class _LifecycleObserver with WidgetsBindingObserver {
  _LifecycleObserver(this.onResumed);

  final VoidCallback onResumed;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      onResumed();
    }
  }
}
