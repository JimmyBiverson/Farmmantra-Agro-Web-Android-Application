import '../../models/user.dart';
import '../../models/product.dart';
import '../../models/order.dart';
import '../../models/payment.dart';
import '../../models/customer.dart';
import '../../models/inventory.dart';
import '../../models/notification.dart';
// Removed unused import

abstract class ApiService {
  Future<void> initialize();

  void setToken(String? token);
  void setUserRole(String? role);

  // Site Settings (public – no auth required)
  Future<Map<String, dynamic>> getSiteSettings();

  // Auth
  Future<Map<String, dynamic>> login(String email, String password);
  Future<void> logout();
  Future<void> refreshToken();
  Future<void> changePassword(String currentPassword, String newPassword);

  // Profile
  Future<User> getProfile();
  Future<User> updateProfile(Map<String, dynamic> data);
  Future<User> uploadAvatar(List<int> fileBytes, String fileName);
  Future<void> updateDeviceToken(String token);

  // Products
  Future<List<ProductCategory>> getCategories();
  Future<List<Product>> getProducts({String? categoryId});
  Future<Product> getProduct(String id);
  Future<List<PriceSlab>> getPriceSlabs(String productId);

  // Orders
  Future<List<Order>> getOrders({String? status, String? franchiseId, String? deliveryStatus});
  Future<Order> getOrder(String id);
  Future<Order> createOrder(Map<String, dynamic> orderData);
  Future<Order> approveOrder(String id, {String? deliveryDate, String? notes});
  Future<Order> declineOrder(String id, String reason);
  Future<Order> adjustOrder(String id, Map<String, dynamic> adjustments);
  /// Franchise accepts a delivered order (implies accepting the T&C).
  /// [receivedQuantities] maps stock-receipt line id → quantity received.
  Future<Order> confirmDelivery(
    String id, {
    String? notes,
    Map<String, double>? receivedQuantities,
  });
  Future<Order> dispatchOrder(
    String id, {
    String means = 'truck',
    String? vehicle,
    String? remarks,
  });
  Future<Order> markOrderDelivered(String id);
  Future<Order> declineDelivery(String id, String reason);

  // Inventory
  Future<List<InventoryItem>> getInventory({String? franchiseId});
  Future<List<InventoryMovement>> getInventoryMovements({String? productId});

  // Sales
  Future<List<Map<String, dynamic>>> getSales({String? dateFrom, String? dateTo});
  Future<Map<String, dynamic>> createSale(Map<String, dynamic> saleData);

  // Customers
  Future<List<Customer>> getCustomers();
  Future<Customer> getCustomer(String id);
  Future<Customer> createCustomer(Map<String, dynamic> customerData);

  // Payments (Franchise)
  Future<List<Payment>> getPayments({String? status});
  Future<Payment> getPayment(String id);
  Future<Payment> submitPayment(Map<String, dynamic> paymentData);
  Future<String> uploadPaymentProof(String paymentId, List<int> fileBytes, String fileName);

  // Payments (Finance)
  Future<List<Payment>> getFinancePayments({String? status, String? franchiseId});
  Future<Payment> getFinancePayment(String id);
  Future<Payment> verifyPayment(String id, {double? verifiedAmount, String? notes});
  Future<Payment> acceptPayment(String id, {String? notes});
  Future<Payment> rejectPayment(String id, String reason);
  Future<Payment> requestPaymentInfo(String id, String note);
  Future<List<Payment>> getPendingFinancePayments();

  // Dashboard
  Future<Map<String, dynamic>> getDashboardStats();
  Future<Map<String, dynamic>> getStaffDashboardStats();
  Future<Map<String, dynamic>> getFinanceDashboardStats();
  Future<List<Map<String, dynamic>>> getSalesAnalytics({String? period});
  Future<AccountSummary> getAccountSummary();

  // Chat
  Future<List<Map<String, dynamic>>> getConversations();
  Future<Map<String, dynamic>> createConversation(
    String subject,
    String initialMessage, {
    String? priority,
  });
  Future<Map<String, dynamic>> getConversation(String id);
  Future<Map<String, dynamic>> sendMessage(String conversationId, String message);
  Future<void> markConversationRead(String conversationId);
  Future<List<Map<String, dynamic>>> getMessagesSince(String conversationId, {int? afterId});

  // Notifications
  Future<List<NotificationItem>> getNotifications({bool? unreadOnly});
  Future<void> markNotificationRead(String id);
  Future<void> markAllNotificationsRead();
  Future<int> getUnreadNotificationCount();

  // Terms & conditions (franchise first login)
  Future<Map<String, dynamic>> getTerms();
  Future<void> acceptTerms();

  // Franchise account statement (credit / debit / running balance)
  Future<Map<String, dynamic>> getAccountStatement({String? from, String? to});

  // Districts of the franchise's region (for registering customers)
  Future<List<Map<String, dynamic>>> getDistricts();

  /// Short-lived link to a printable document (opened in the browser).
  Future<String> getPrintLink(
    String type,
    String id, {
    Map<String, String>? query,
  });
}
