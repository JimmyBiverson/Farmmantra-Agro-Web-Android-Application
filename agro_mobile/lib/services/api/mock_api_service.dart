import 'dart:math';
import '../../core/enums/user_role.dart';
import '../../core/enums/app_enums.dart';
import '../../models/user.dart';
import '../../models/product.dart';
import '../../models/order.dart';
import '../../models/payment.dart';
import '../../models/customer.dart';
import '../../models/inventory.dart';
import '../../models/notification.dart';
import 'api_service.dart';

class MockApiService implements ApiService {
  User? _currentUser;
  String? _token;
  String? _userRole;
  final List<NotificationItem> _notifications = [];
  int _orderCounter = 1000;
  int _paymentCounter = 5000;

  bool get isAuthenticated => _token != null;
  String? get activeRole => _userRole;

  @override
  void setToken(String? token) {
    _token = token;
  }

  @override
  void setUserRole(String? role) {
    _userRole = role;
  }

  @override
  Future<void> initialize() async {
    await Future.delayed(const Duration(milliseconds: 300));
  }

  @override
  Future<Map<String, dynamic>> getSiteSettings() async {
    return {
      'site_name': 'Farmmantra Agro Chemicals',
      'site_tagline': 'Growing Uganda, One Farm at a Time',
      'logo_url': null,
      'favicon_url': null,
      'primary_color': '#2E7D32',
      'secondary_color': '#1B5E20',
      'contact_email': 'info@farmmantra.co.ug',
      'contact_phone': '+256 700 000001',
      'address': 'Kampala, Uganda',
      'currency_symbol': 'UGX',
      'currency_code': 'UGX',
    };
  }

  Future<void> _simulateDelay() async {
    await Future.delayed(Duration(milliseconds: 500 + Random().nextInt(500)));
  }

  Map<String, dynamic> _mockLoginResponse(String email, UserRole role) {
    final user = User(
      id: '1',
      name: role == UserRole.franchisePartner
          ? 'Kampala Franchise'
          : 'John Staff',
      email: email,
      phone: '+256700000000',
      role: role,
      franchiseId: role == UserRole.franchisePartner ? 'F001' : null,
      franchiseName: role == UserRole.franchisePartner
          ? 'Kampala Franchise Partner'
          : null,
      createdAt: DateTime.now(),
    );
    _currentUser = user;
    _generateMockNotifications();
    return {
      'token': 'mock_jwt_token_${DateTime.now().millisecondsSinceEpoch}',
      'refresh_token': 'mock_refresh_token',
      'user': user.toJson(),
    };
  }

  @override
  Future<Map<String, dynamic>> login(String email, String password) async {
    await _simulateDelay();
    if (email.isEmpty || password.isEmpty) {
      throw Exception('Email and password are required');
    }
    if (password.length < 6) {
      throw Exception('Invalid credentials');
    }

    final role = email.toLowerCase().contains('staff')
        ? UserRole.farmmantraStaff
        : UserRole.franchisePartner;

    return _mockLoginResponse(email, role);
  }

  @override
  Future<void> logout() async {
    await _simulateDelay();
    _currentUser = null;
  }

  @override
  Future<void> refreshToken() async {
    await _simulateDelay();
  }

  @override
  Future<void> changePassword(
    String currentPassword,
    String newPassword,
  ) async {
    await _simulateDelay();
  }

  @override
  Future<User> getProfile() async {
    await _simulateDelay();
    if (_currentUser != null) return _currentUser!;
    return User(
      id: '1',
      name: 'User',
      email: 'user@farmmantra.co.ug',
      phone: '+256700000000',
      role: _userRole == 'farmmantraStaff'
          ? UserRole.farmmantraStaff
          : UserRole.franchisePartner,
    );
  }

  @override
  Future<User> updateProfile(Map<String, dynamic> data) async {
    await _simulateDelay();
    final prefs = data['notification_preferences'] is Map
        ? Map<String, dynamic>.from(data['notification_preferences'])
        : null;
    return _currentUser!.copyWith(
      name: data['name'] ?? _currentUser!.name,
      phone: data['phone'] ?? _currentUser!.phone,
      address: data['address'] ?? _currentUser!.address,
      notificationPreferences: prefs,
    );
  }

  @override
  Future<User> uploadAvatar(List<int> fileBytes, String fileName) async {
    await _simulateDelay();
    return _currentUser!.copyWith(avatarUrl: 'mock-avatar-$fileName');
  }

  @override
  Future<void> updateDeviceToken(String token) async {
    await _simulateDelay();
  }

  @override
  Future<List<ProductCategory>> getCategories() async {
    await _simulateDelay();
    return ProductCategory.defaultCategories;
  }

  @override
  Future<List<Product>> getProducts({String? categoryId}) async {
    await _simulateDelay();
    final products = [
      Product(
        id: '1',
        name: 'Roundup PowerMax',
        description: 'Broad-spectrum systemic herbicide',
        categoryId: '1',
        categoryName: 'Herbicides',
        unitOfMeasure: 'Litres',
        packagingDetails: '1L, 5L, 20L',
        standardPrice: 45000,
        priceSlabs: const [
          PriceSlab(
            id: 's1',
            productId: '1',
            minQuantity: 1,
            maxQuantity: 9,
            pricePerUnit: 45000,
          ),
          PriceSlab(
            id: 's2',
            productId: '1',
            minQuantity: 10,
            maxQuantity: 49,
            pricePerUnit: 42000,
          ),
          PriceSlab(
            id: 's3',
            productId: '1',
            minQuantity: 50,
            pricePerUnit: 38000,
          ),
        ],
      ),
      Product(
        id: '2',
        name: 'Thunder 145-SE',
        description: 'Organophosphate insecticide',
        categoryId: '2',
        categoryName: 'Insecticides',
        unitOfMeasure: 'Litres',
        packagingDetails: '1L, 5L',
        standardPrice: 35000,
        priceSlabs: const [
          PriceSlab(
            id: 's4',
            productId: '2',
            minQuantity: 1,
            maxQuantity: 19,
            pricePerUnit: 35000,
          ),
          PriceSlab(
            id: 's5',
            productId: '2',
            minQuantity: 20,
            pricePerUnit: 32000,
          ),
        ],
      ),
      Product(
        id: '3',
        name: 'Ridomil Gold',
        description: 'Systemic fungicide for crop protection',
        categoryId: '3',
        categoryName: 'Fungicides',
        unitOfMeasure: 'Kg',
        packagingDetails: '1kg, 5kg',
        standardPrice: 55000,
        priceSlabs: const [
          PriceSlab(
            id: 's6',
            productId: '3',
            minQuantity: 1,
            maxQuantity: 9,
            pricePerUnit: 55000,
          ),
          PriceSlab(
            id: 's7',
            productId: '3',
            minQuantity: 10,
            pricePerUnit: 50000,
          ),
        ],
      ),
      Product(
        id: '4',
        name: 'Neem Oil Extract',
        description: 'Organic pest management solution',
        categoryId: '4',
        categoryName: 'Organic Products',
        unitOfMeasure: 'Litres',
        packagingDetails: '500ml, 1L',
        standardPrice: 25000,
        priceSlabs: const [
          PriceSlab(
            id: 's8',
            productId: '4',
            minQuantity: 1,
            maxQuantity: 24,
            pricePerUnit: 25000,
          ),
          PriceSlab(
            id: 's9',
            productId: '4',
            minQuantity: 25,
            pricePerUnit: 22000,
          ),
        ],
      ),
      Product(
        id: '5',
        name: 'NAARI 505 Maize Seed',
        description: 'High-yield hybrid maize seed',
        categoryId: '5',
        categoryName: 'Seeds',
        unitOfMeasure: 'Kg',
        packagingDetails: '2kg, 10kg',
        standardPrice: 18000,
        priceSlabs: const [
          PriceSlab(
            id: 's10',
            productId: '5',
            minQuantity: 1,
            maxQuantity: 9,
            pricePerUnit: 18000,
          ),
          PriceSlab(
            id: 's11',
            productId: '5',
            minQuantity: 10,
            maxQuantity: 49,
            pricePerUnit: 16000,
          ),
          PriceSlab(
            id: 's12',
            productId: '5',
            minQuantity: 50,
            pricePerUnit: 14000,
          ),
        ],
      ),
      Product(
        id: '6',
        name: 'NPK 17:17:17',
        description: 'Compound fertilizer for general use',
        categoryId: '6',
        categoryName: 'Fertilizers',
        unitOfMeasure: 'Kg',
        packagingDetails: '5kg, 50kg',
        standardPrice: 12000,
        priceSlabs: const [
          PriceSlab(
            id: 's13',
            productId: '6',
            minQuantity: 1,
            maxQuantity: 19,
            pricePerUnit: 12000,
          ),
          PriceSlab(
            id: 's14',
            productId: '6',
            minQuantity: 20,
            maxQuantity: 99,
            pricePerUnit: 10500,
          ),
          PriceSlab(
            id: 's15',
            productId: '6',
            minQuantity: 100,
            pricePerUnit: 9000,
          ),
        ],
      ),
      Product(
        id: '7',
        name: 'Gibberellic Acid 10% WP',
        description: 'Plant growth regulator',
        categoryId: '7',
        categoryName: 'PGR',
        unitOfMeasure: 'Kg',
        packagingDetails: '250g, 1kg',
        standardPrice: 85000,
        priceSlabs: const [
          PriceSlab(
            id: 's16',
            productId: '7',
            minQuantity: 1,
            maxQuantity: 4,
            pricePerUnit: 85000,
          ),
          PriceSlab(
            id: 's17',
            productId: '7',
            minQuantity: 5,
            pricePerUnit: 78000,
          ),
        ],
      ),
    ];

    if (categoryId != null) {
      return products.where((p) => p.categoryId == categoryId).toList();
    }
    return products;
  }

  @override
  Future<Product> getProduct(String id) async {
    await _simulateDelay();
    final products = await getProducts();
    return products.firstWhere((p) => p.id == id);
  }

  @override
  Future<List<PriceSlab>> getPriceSlabs(String productId) async {
    await _simulateDelay();
    final product = await getProduct(productId);
    return product.priceSlabs;
  }

  @override
  Future<List<Order>> getOrders({
    String? status,
    String? franchiseId,
    String? deliveryStatus,
  }) async {
    await _simulateDelay();
    final now = DateTime.now();
    final orders = [
      Order(
        id: 'ORD-1001',
        franchiseId: 'F001',
        franchiseName: 'Kampala Franchise',
        items: const [
          OrderItem(
            id: '1',
            productId: '1',
            productName: 'Roundup PowerMax',
            categoryName: 'Herbicides',
            quantity: 20,
            unitPrice: 42000,
            totalPrice: 840000,
          ),
          OrderItem(
            id: '2',
            productId: '6',
            productName: 'NPK 17:17:17',
            categoryName: 'Fertilizers',
            quantity: 50,
            unitPrice: 10500,
            totalPrice: 525000,
          ),
        ],
        totalAmount: 1365000,
        status: 'pending',
        createdAt: now.subtract(const Duration(hours: 3)),
        updatedAt: now.subtract(const Duration(hours: 3)),
      ),
      Order(
        id: 'ORD-1002',
        franchiseId: 'F001',
        franchiseName: 'Kampala Franchise',
        items: const [
          OrderItem(
            id: '3',
            productId: '2',
            productName: 'Thunder 145-SE',
            categoryName: 'Insecticides',
            quantity: 10,
            unitPrice: 35000,
            totalPrice: 350000,
          ),
        ],
        totalAmount: 350000,
        status: 'approved',
        expectedDeliveryDate: now.add(const Duration(days: 3)),
        createdAt: now.subtract(const Duration(days: 1)),
        updatedAt: now.subtract(const Duration(hours: 12)),
      ),
      Order(
        id: 'ORD-1003',
        franchiseId: 'F002',
        franchiseName: 'Jinja Franchise',
        items: const [
          OrderItem(
            id: '4',
            productId: '3',
            productName: 'Ridomil Gold',
            categoryName: 'Fungicides',
            quantity: 5,
            unitPrice: 55000,
            totalPrice: 275000,
          ),
        ],
        totalAmount: 275000,
        status: 'delivered',
        deliveryStatus: 'delivered',
        paymentVerifiedCount: 1,
        deliveredAt: now.subtract(const Duration(days: 2)),
        createdAt: now.subtract(const Duration(days: 7)),
        updatedAt: now.subtract(const Duration(days: 2)),
      ),
    ];

    var filtered = orders;
    if (status != null) {
      filtered = filtered.where((o) => o.status == status).toList();
    }
    if (deliveryStatus != null) {
      filtered = filtered
          .where((o) => o.deliveryStatus == deliveryStatus)
          .toList();
    }
    return filtered;
  }

  @override
  Future<Order> dispatchOrder(
    String id, {
    String means = 'truck',
    String? vehicle,
    String? remarks,
  }) async {
    await _simulateDelay();
    final order = await getOrder(id);
    return Order(
      id: order.id,
      franchiseId: order.franchiseId,
      franchiseName: order.franchiseName,
      items: order.items,
      totalAmount: order.totalAmount,
      taxAmount: order.taxAmount,
      status: 'approved',
      deliveryStatus: 'out_for_delivery',
      expectedDeliveryDate: order.expectedDeliveryDate,
      createdAt: order.createdAt,
      updatedAt: DateTime.now(),
      dispatchMeans: means,
      dispatchVehicle: vehicle,
      dispatchRemarks: remarks,
      dispatchedAt: DateTime.now(),
    );
  }

  @override
  Future<Order> markOrderDelivered(String id) async {
    await _simulateDelay();
    final order = await getOrder(id);
    return Order(
      id: order.id,
      franchiseId: order.franchiseId,
      franchiseName: order.franchiseName,
      items: order.items,
      totalAmount: order.totalAmount,
      taxAmount: order.taxAmount,
      status: 'delivered',
      deliveryStatus: 'delivered',
      paymentVerifiedCount: 1,
      deliveredAt: DateTime.now(),
      createdAt: order.createdAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Order> declineDelivery(String id, String reason) async {
    await _simulateDelay();
    final order = await getOrder(id);
    return Order(
      id: order.id,
      franchiseId: order.franchiseId,
      franchiseName: order.franchiseName,
      items: order.items,
      totalAmount: order.totalAmount,
      taxAmount: order.taxAmount,
      status: 'approved',
      deliveryStatus: 'declined',
      deliveryDeclinedReason: reason,
      createdAt: order.createdAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Order> getOrder(String id) async {
    await _simulateDelay();
    final orders = await getOrders();
    return orders.firstWhere((o) => o.id == id);
  }

  @override
  Future<Order> createOrder(Map<String, dynamic> orderData) async {
    await _simulateDelay();
    _orderCounter++;
    return Order(
      id: 'ORD-$_orderCounter',
      franchiseId: 'F001',
      franchiseName: 'Kampala Franchise',
      items: (orderData['items'] as List? ?? [])
          .map((i) => OrderItem.fromJson(i))
          .toList(),
      totalAmount: (orderData['total_amount'] ?? 0).toDouble(),
      status: 'pending',
      createdAt: DateTime.now(),
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Order> approveOrder(
    String id, {
    String? deliveryDate,
    String? notes,
  }) async {
    await _simulateDelay();
    final order = await getOrder(id);
    return Order(
      id: order.id,
      franchiseId: order.franchiseId,
      franchiseName: order.franchiseName,
      items: order.items,
      totalAmount: order.totalAmount,
      status: 'approved',
      expectedDeliveryDate: deliveryDate != null
          ? DateTime.tryParse(deliveryDate)
          : DateTime.now().add(const Duration(days: 3)),
      staffNotes: notes,
      createdAt: order.createdAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Order> declineOrder(String id, String reason) async {
    await _simulateDelay();
    final order = await getOrder(id);
    return Order(
      id: order.id,
      franchiseId: order.franchiseId,
      franchiseName: order.franchiseName,
      items: order.items,
      totalAmount: order.totalAmount,
      status: 'declined',
      declineReason: reason,
      createdAt: order.createdAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Order> adjustOrder(String id, Map<String, dynamic> adjustments) async {
    await _simulateDelay();
    final order = await getOrder(id);
    return Order(
      id: order.id,
      franchiseId: order.franchiseId,
      franchiseName: order.franchiseName,
      items: order.items,
      totalAmount:
          adjustments['adjusted_amount']?.toDouble() ?? order.totalAmount,
      adjustedAmount: adjustments['adjusted_amount']?.toDouble(),
      status: 'adjusted',
      adjustmentNotes: adjustments['notes'],
      createdAt: order.createdAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Order> confirmDelivery(
    String id, {
    String? notes,
    Map<String, double>? receivedQuantities,
  }) async {
    await _simulateDelay();
    final order = await getOrder(id);
    return Order(
      id: order.id,
      franchiseId: order.franchiseId,
      franchiseName: order.franchiseName,
      items: order.items,
      totalAmount: order.totalAmount,
      status: 'delivered',
      deliveredAt: DateTime.now(),
      staffNotes: notes,
      createdAt: order.createdAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<List<InventoryItem>> getInventory({String? franchiseId}) async {
    await _simulateDelay();
    final now = DateTime.now();
    return [
      InventoryItem(
        id: 'inv-1',
        productId: '1',
        productName: 'Roundup PowerMax',
        categoryName: 'Herbicides',
        quantity: 45,
        unitOfMeasure: 'Litres',
        unitCost: 42000,
        totalValue: 1890000,
        reorderLevel: 10,
        alertLevel: InventoryAlertLevel.normal,
        lastUpdated: now,
      ),
      InventoryItem(
        id: 'inv-2',
        productId: '2',
        productName: 'Thunder 145-SE',
        categoryName: 'Insecticides',
        quantity: 8,
        unitOfMeasure: 'Litres',
        unitCost: 35000,
        totalValue: 280000,
        reorderLevel: 10,
        alertLevel: InventoryAlertLevel.low,
        lastUpdated: now,
      ),
      InventoryItem(
        id: 'inv-3',
        productId: '3',
        productName: 'Ridomil Gold',
        categoryName: 'Fungicides',
        quantity: 2,
        unitOfMeasure: 'Kg',
        unitCost: 55000,
        totalValue: 110000,
        reorderLevel: 5,
        alertLevel: InventoryAlertLevel.critical,
        lastUpdated: now,
      ),
      InventoryItem(
        id: 'inv-4',
        productId: '5',
        productName: 'NAARI 505 Maize Seed',
        categoryName: 'Seeds',
        quantity: 120,
        unitOfMeasure: 'Kg',
        unitCost: 18000,
        totalValue: 2160000,
        reorderLevel: 20,
        alertLevel: InventoryAlertLevel.normal,
        lastUpdated: now,
      ),
      InventoryItem(
        id: 'inv-5',
        productId: '6',
        productName: 'NPK 17:17:17',
        categoryName: 'Fertilizers',
        quantity: 0,
        unitOfMeasure: 'Kg',
        unitCost: 12000,
        totalValue: 0,
        reorderLevel: 15,
        alertLevel: InventoryAlertLevel.outOfStock,
        lastUpdated: now,
      ),
    ];
  }

  @override
  Future<List<InventoryMovement>> getInventoryMovements({
    String? productId,
  }) async {
    await _simulateDelay();
    final now = DateTime.now();
    return [
      InventoryMovement(
        id: 'mov-1',
        productName: 'Roundup PowerMax',
        type: 'inbound',
        quantity: 50,
        previousQuantity: 0,
        newQuantity: 50,
        referenceId: 'ORD-0999',
        createdAt: now.subtract(const Duration(days: 5)),
      ),
      InventoryMovement(
        id: 'mov-2',
        productName: 'Roundup PowerMax',
        type: 'outbound',
        quantity: 5,
        previousQuantity: 50,
        newQuantity: 45,
        referenceId: 'SAL-001',
        createdAt: now.subtract(const Duration(days: 2)),
      ),
    ];
  }

  @override
  Future<List<Map<String, dynamic>>> getSales({
    String? dateFrom,
    String? dateTo,
  }) async {
    await _simulateDelay();
    return [
      {
        'id': 'SAL-001',
        'customer_name': 'John Farmer',
        'date': DateTime.now()
            .subtract(const Duration(days: 1))
            .toIso8601String(),
        'total_amount': 450000,
        'items': 2,
      },
      {
        'id': 'SAL-002',
        'customer_name': 'Mary Trader',
        'date': DateTime.now()
            .subtract(const Duration(days: 3))
            .toIso8601String(),
        'total_amount': 250000,
        'items': 1,
      },
    ];
  }

  @override
  Future<Map<String, dynamic>> createSale(Map<String, dynamic> saleData) async {
    await _simulateDelay();
    return {
      'id': 'SAL-${DateTime.now().millisecondsSinceEpoch}',
      'status': 'completed',
      'message': 'Sale recorded successfully',
    };
  }

  @override
  Future<List<Customer>> getCustomers() async {
    await _simulateDelay();
    return [
      Customer(
        id: 'c1',
        name: 'John Farmer',
        phone: '+256712345678',
        location: 'Kampala',
        createdAt: DateTime.now().subtract(const Duration(days: 30)),
      ),
      Customer(
        id: 'c2',
        name: 'Mary Trader',
        phone: '+256787654321',
        location: 'Mukono',
        createdAt: DateTime.now().subtract(const Duration(days: 15)),
      ),
    ];
  }

  @override
  Future<Customer> getCustomer(String id) async {
    await _simulateDelay();
    final customers = await getCustomers();
    return customers.firstWhere((c) => c.id == id);
  }

  @override
  Future<Customer> createCustomer(Map<String, dynamic> customerData) async {
    await _simulateDelay();
    return Customer(
      id: 'c-${DateTime.now().millisecondsSinceEpoch}',
      name: customerData['name'] ?? '',
      phone: customerData['phone'] ?? '',
      email: customerData['email'],
      location: customerData['location'],
      createdAt: DateTime.now(),
    );
  }

  @override
  Future<List<Payment>> getPayments({String? status}) async {
    await _simulateDelay();
    final now = DateTime.now();
    return [
      Payment(
        id: 'PAY-5001',
        franchiseId: 'F001',
        franchiseName: 'Kampala Franchise',
        amount: 2000000,
        transactionReference: 'REF-2024-001',
        bankName: 'Stanbic Bank',
        paymentMethod: 'Bank Transfer',
        status: 'pending',
        submittedAt: now.subtract(const Duration(hours: 6)),
        updatedAt: now.subtract(const Duration(hours: 6)),
      ),
      Payment(
        id: 'PAY-5002',
        franchiseId: 'F001',
        franchiseName: 'Kampala Franchise',
        amount: 1500000,
        transactionReference: 'REF-2024-002',
        bankName: 'Centenary Bank',
        paymentMethod: 'Mobile Money',
        status: 'accepted',
        verifiedBy: 'Finance Admin',
        verifiedAt: now.subtract(const Duration(days: 1)),
        submittedAt: now.subtract(const Duration(days: 2)),
        updatedAt: now.subtract(const Duration(days: 1)),
      ),
    ];
  }

  @override
  Future<Payment> getPayment(String id) async {
    await _simulateDelay();
    final payments = await getPayments();
    return payments.firstWhere((p) => p.id == id);
  }

  @override
  Future<Payment> submitPayment(Map<String, dynamic> paymentData) async {
    await _simulateDelay();
    _paymentCounter++;
    return Payment(
      id: 'PAY-$_paymentCounter',
      franchiseId: 'F001',
      franchiseName: 'Kampala Franchise',
      amount: (paymentData['amount'] ?? 0).toDouble(),
      transactionReference: paymentData['transaction_reference'] ?? '',
      bankName: paymentData['bank_name'],
      paymentMethod: paymentData['payment_method'],
      status: 'pending',
      submittedAt: DateTime.now(),
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<String> uploadPaymentProof(
    String paymentId,
    List<int> fileBytes,
    String fileName,
  ) async {
    await _simulateDelay();
    return 'https://storage.farmmantra.co.ug/payments/$paymentId/$fileName';
  }

  @override
  Future<Payment> verifyPayment(
    String id, {
    double? verifiedAmount,
    String? notes,
  }) async {
    await _simulateDelay();
    final payment = await getPayment(id);
    return Payment(
      id: payment.id,
      franchiseId: payment.franchiseId,
      franchiseName: payment.franchiseName,
      amount: payment.amount,
      transactionReference: payment.transactionReference,
      bankName: payment.bankName,
      paymentMethod: payment.paymentMethod,
      status: 'verified',
      proofUrl: payment.proofUrl,
      verifiedBy: 'Finance Team',
      verifiedAt: DateTime.now(),
      submittedAt: payment.submittedAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Payment> acceptPayment(String id, {String? notes}) async {
    await _simulateDelay();
    final payment = await getPayment(id);
    return Payment(
      id: payment.id,
      franchiseId: payment.franchiseId,
      franchiseName: payment.franchiseName,
      amount: payment.amount,
      transactionReference: payment.transactionReference,
      bankName: payment.bankName,
      paymentMethod: payment.paymentMethod,
      status: 'accepted',
      proofUrl: payment.proofUrl,
      verifiedBy: 'Finance Admin',
      verifiedAt: DateTime.now(),
      submittedAt: payment.submittedAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Payment> rejectPayment(String id, String reason) async {
    await _simulateDelay();
    final payment = await getPayment(id);
    return Payment(
      id: payment.id,
      franchiseId: payment.franchiseId,
      franchiseName: payment.franchiseName,
      amount: payment.amount,
      transactionReference: payment.transactionReference,
      status: 'rejected',
      rejectionReason: reason,
      submittedAt: payment.submittedAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<Payment> requestPaymentInfo(String id, String note) async {
    await _simulateDelay();
    final payment = await getPayment(id);
    return Payment(
      id: payment.id,
      franchiseId: payment.franchiseId,
      franchiseName: payment.franchiseName,
      amount: payment.amount,
      transactionReference: payment.transactionReference,
      status: 'info_requested',
      infoRequestNote: note,
      submittedAt: payment.submittedAt,
      updatedAt: DateTime.now(),
    );
  }

  @override
  Future<List<Payment>> getFinancePayments({
    String? status,
    String? franchiseId,
  }) async {
    await _simulateDelay();
    return await getPayments(status: status);
  }

  @override
  Future<Payment> getFinancePayment(String id) async {
    await _simulateDelay();
    return await getPayment(id);
  }

  @override
  Future<List<Payment>> getPendingFinancePayments() async {
    await _simulateDelay();
    final payments = await getPayments();
    return payments.where((p) => p.status == 'pending').toList();
  }

  @override
  Future<Map<String, dynamic>> getStaffDashboardStats() async {
    await _simulateDelay();
    return {
      'summary': {
        'pending_orders': 4,
        'awaiting_payment_orders': 3,
        'deliveries_today': 2,
        'approved_orders_today': 5,
        'pending_payments_count': 2,
        'low_stock_products': 1,
        'new_support_messages': 1,
      },
      'banner': {
        'pending_orders_today': 2,
        'awaiting_payment': 3,
        'deliveries_today': 2,
        'new_support_messages': 1,
      },
    };
  }

  @override
  Future<Map<String, dynamic>> getFinanceDashboardStats() async {
    await _simulateDelay();
    return {
      'summary': {
        'pending_payments_count': 8,
        'pending_payments_total': '4230287.00',
        'accepted_this_month': 11,
        'accepted_amount_this_month': '4415904.00',
        'total_outstanding': '26056586.00',
        'total_collected_ytd': '9091921.00',
      },
      'outstanding_by_franchise': [],
      'payment_trend': [],
      'recent_pending': [],
      'payment_status_breakdown': [],
    };
  }

  @override
  Future<Map<String, dynamic>> getDashboardStats() async {
    await _simulateDelay();
    return {
      'total_sales_this_month': 12500000,
      'total_sales_ytd': 145000000,
      'total_orders_pending': 3,
      'total_orders_this_month': 12,
      'inventory_value': 25000000,
      'outstanding_balance': 5000000,
      'credit_limit': 20000000,
      'low_stock_items': 2,
      'sales_target': 20000000,
      'sales_achievement': 62.5,
      'sales_by_category': [
        {'category': 'Herbicides', 'amount': 5000000},
        {'category': 'Insecticides', 'amount': 3000000},
        {'category': 'Fertilizers', 'amount': 2500000},
        {'category': 'Seeds', 'amount': 2000000},
      ],
    };
  }

  @override
  Future<List<Map<String, dynamic>>> getSalesAnalytics({String? period}) async {
    await _simulateDelay();
    return [
      {'month': 'Jan', 'amount': 8000000},
      {'month': 'Feb', 'amount': 10000000},
      {'month': 'Mar', 'amount': 12500000},
      {'month': 'Apr', 'amount': 9500000},
      {'month': 'May', 'amount': 14000000},
      {'month': 'Jun', 'amount': 12500000},
    ];
  }

  @override
  Future<AccountSummary> getAccountSummary() async {
    await _simulateDelay();
    return const AccountSummary(
      totalSales: 145000000,
      totalPayments: 140000000,
      outstandingBalance: 5000000,
      creditLimit: 20000000,
    );
  }

  @override
  Future<List<NotificationItem>> getNotifications({bool? unreadOnly}) async {
    await _simulateDelay();
    if (unreadOnly == true) {
      return _notifications.where((n) => !n.isRead).toList();
    }
    return List.from(_notifications);
  }

  @override
  Future<void> markNotificationRead(String id) async {
    await _simulateDelay();
    final index = _notifications.indexWhere((n) => n.id == id);
    if (index != -1) {
      _notifications[index] = _notifications[index].copyWith(isRead: true);
    }
  }

  @override
  Future<void> markAllNotificationsRead() async {
    await _simulateDelay();
    for (var i = 0; i < _notifications.length; i++) {
      _notifications[i] = _notifications[i].copyWith(isRead: true);
    }
  }

  @override
  Future<int> getUnreadNotificationCount() async {
    return _notifications.where((n) => !n.isRead).length;
  }

  @override
  Future<List<Map<String, dynamic>>> getConversations() async {
    await _simulateDelay();
    return [
      {
        'id': '1',
        'subject': 'Order issue for ORD-1002',
        'priority': 'normal',
        'status': 'open',
        'created_at': DateTime.now()
            .subtract(const Duration(hours: 2))
            .toIso8601String(),
        'messages': [
          {
            'id': 'm1',
            'sender_id': '1',
            'message':
                'Hello, my order is missing some standard packaging details.',
            'created_at': DateTime.now()
                .subtract(const Duration(hours: 2))
                .toIso8601String(),
          },
          {
            'id': 'm2',
            'sender_id': 'admin',
            'message':
                'Understood. We are checking this with the warehouse staff.',
            'created_at': DateTime.now()
                .subtract(const Duration(hours: 1))
                .toIso8601String(),
          },
        ],
      },
    ];
  }

  @override
  Future<Map<String, dynamic>> createConversation(
    String subject,
    String initialMessage, {
    String? priority,
  }) async {
    await _simulateDelay();
    return {
      'id': 'conv_new',
      'subject': subject,
      'priority': priority ?? 'normal',
      'status': 'open',
      'created_at': DateTime.now().toIso8601String(),
      'messages': [
        {
          'id': 'm_init',
          'sender_id': '1',
          'message': initialMessage,
          'created_at': DateTime.now().toIso8601String(),
        },
      ],
    };
  }

  @override
  Future<Map<String, dynamic>> getConversation(String id) async {
    await _simulateDelay();
    return {
      'id': id,
      'subject': 'Mock ticket $id',
      'priority': 'normal',
      'status': 'open',
      'created_at': DateTime.now()
          .subtract(const Duration(hours: 2))
          .toIso8601String(),
      'messages': [
        {
          'id': 'm_mock',
          'sender_id': '1',
          'message': 'Initial test message for conversation $id',
          'created_at': DateTime.now()
              .subtract(const Duration(hours: 2))
              .toIso8601String(),
        },
      ],
    };
  }

  @override
  Future<Map<String, dynamic>> sendMessage(
    String conversationId,
    String message,
  ) async {
    await _simulateDelay();
    return {
      'id': 'msg_${DateTime.now().millisecondsSinceEpoch}',
      'conversation_id': conversationId,
      'sender_id': '1',
      'message': message,
      'created_at': DateTime.now().toIso8601String(),
    };
  }

  @override
  Future<void> markConversationRead(String conversationId) async {
    await _simulateDelay();
  }

  @override
  Future<List<Map<String, dynamic>>> getMessagesSince(
    String conversationId, {
    int? afterId,
  }) async {
    await _simulateDelay();
    return [];
  }

  void _generateMockNotifications() {
    _notifications.clear();
    final now = DateTime.now();
    _notifications.addAll([
      NotificationItem(
        id: 'n1',
        title: 'Order Approved',
        message:
            'Your order ORD-1002 has been approved. Expected delivery: 3 days.',
        type: 'order',
        isRead: false,
        createdAt: now.subtract(const Duration(hours: 1)),
        referenceId: 'ORD-1002',
        referenceType: 'order',
      ),
      NotificationItem(
        id: 'n2',
        title: 'Payment Received',
        message: 'Payment PAY-5002 of UGX 1,500,000 has been accepted.',
        type: 'payment',
        isRead: false,
        createdAt: now.subtract(const Duration(hours: 4)),
        referenceId: 'PAY-5002',
        referenceType: 'payment',
      ),
      NotificationItem(
        id: 'n3',
        title: 'Low Stock Alert',
        message:
            'Thunder 145-SE is below reorder level. Current stock: 8 litres.',
        type: 'inventory',
        isRead: true,
        createdAt: now.subtract(const Duration(days: 1)),
      ),
      NotificationItem(
        id: 'n4',
        title: 'New Price Update',
        message:
            'Product prices have been updated. Check the latest catalogue.',
        type: 'system',
        isRead: true,
        createdAt: now.subtract(const Duration(days: 3)),
      ),
    ]);
  }

  @override
  Future<Map<String, dynamic>> getTerms() async {
    await _simulateDelay();
    return {
      'text': 'Demo terms and conditions.',
      'version': 1,
      'needs_acceptance': false,
    };
  }

  @override
  Future<void> acceptTerms() async {
    await _simulateDelay();
  }

  @override
  Future<Map<String, dynamic>> getAccountStatement({
    String? from,
    String? to,
  }) async {
    await _simulateDelay();
    return {
      'franchise': {'name': 'Demo Franchise', 'code': 'DEMO'},
      'period': {'from': from, 'to': to},
      'opening_balance': 0,
      'transactions': [
        {
          'date': '2026-08-01',
          'type': 'purchase',
          'type_label': 'Purchase',
          'description': 'Purchase — demo order',
          'reference_number': 'ORD-DEMO-0001',
          'credit': 1000000,
          'debit': 0,
          'balance': 1000000,
        },
        {
          'date': '2026-08-02',
          'type': 'payment',
          'type_label': 'Payment',
          'description': 'Payment (Mobile Money)',
          'reference_number': 'PAY-DEMO-0001',
          'credit': 0,
          'debit': 900000,
          'balance': 100000,
        },
      ],
      'totals': {'credit': 1000000, 'debit': 900000},
      'closing_balance': 100000,
      'current_balance': 100000,
    };
  }

  @override
  Future<List<Map<String, dynamic>>> getDistricts() async {
    await _simulateDelay();
    return [
      {'id': 1, 'name': 'Gulu'},
      {'id': 2, 'name': 'Amuru'},
    ];
  }

  @override
  Future<String> getPrintLink(
    String type,
    String id, {
    Map<String, String>? query,
  }) async {
    await _simulateDelay();
    return 'https://example.com/print/$type/$id';
  }
}
