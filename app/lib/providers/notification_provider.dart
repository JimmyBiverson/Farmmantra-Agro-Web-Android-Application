import 'package:flutter/material.dart';
import '../core/exceptions/app_exception.dart';
import '../models/notification.dart';
import '../services/api/api_service.dart';
import '../services/notification_service.dart';

class NotificationProvider extends ChangeNotifier {
  final ApiService _apiService;

  NotificationProvider({required ApiService apiService})
    : _apiService = apiService;

  List<NotificationItem> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = false;
  String? _error;
  bool _hasLoaded = false;
  NotificationItem? _latestIncoming;

  List<NotificationItem> get notifications => _notifications;
  int get unreadCount => _unreadCount;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isEmpty => _notifications.isEmpty && !_isLoading && _error == null;

  NotificationItem? takeLatestAlert() {
    final latest = _latestIncoming;
    _latestIncoming = null;
    return latest;
  }

  Future<void> loadNotifications({
    bool? unreadOnly,
    bool silent = false,
  }) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      final previousIds = _notifications.map((n) => n.id).toSet();
      final loaded = await _apiService.getNotifications(unreadOnly: unreadOnly);
      if (silent && _hasLoaded) {
        final incoming = loaded.where(
          (n) => !previousIds.contains(n.id) && !n.isRead,
        );
        if (incoming.isNotEmpty) {
          _latestIncoming = incoming.first;
        }
      }
      _notifications = loaded;
      if (unreadOnly != true) {
        _unreadCount = _notifications.where((n) => !n.isRead).length;
        await NotificationService().setUnreadCount(_unreadCount);
      }
      _hasLoaded = true;
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) _error = errorMessageOf(e, 'Failed to load notifications');
      notifyListeners();
    }
  }

  Future<void> markAsRead(String id) async {
    try {
      await _apiService.markNotificationRead(id);
      final index = _notifications.indexWhere((n) => n.id == id);
      if (index != -1 && !_notifications[index].isRead) {
        _notifications[index] = _notifications[index].copyWith(isRead: true);
        _unreadCount = (_unreadCount - 1).clamp(0, _notifications.length);
        await NotificationService().setUnreadCount(_unreadCount);
        notifyListeners();
      }
    } catch (e) {
      _error = errorMessageOf(e, 'Failed to mark notification as read');
      notifyListeners();
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await _apiService.markAllNotificationsRead();
      _notifications = _notifications
          .map((n) => n.copyWith(isRead: true))
          .toList();
      _unreadCount = 0;
      await NotificationService().setUnreadCount(0);
      notifyListeners();
    } catch (e) {
      _error = errorMessageOf(e, 'Failed to mark all as read');
      notifyListeners();
    }
  }

  Future<void> refreshUnreadCount() async {
    try {
      _unreadCount = await _apiService.getUnreadNotificationCount();
      await NotificationService().setUnreadCount(_unreadCount);
      notifyListeners();
    } catch (_) {
      // Silently fail for background count refresh
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
