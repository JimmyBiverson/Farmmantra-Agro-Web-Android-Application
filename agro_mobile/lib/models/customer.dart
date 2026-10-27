class Customer {
  final String id;
  final String name;
  final String phone;
  final String? email;
  final String? location;
  final String? notes;
  final DateTime createdAt;

  /// 'retailer' or 'farmer' — decides which admin-fixed price applies.
  final String customerType;
  final String? districtName;

  const Customer({
    required this.id,
    required this.name,
    required this.phone,
    this.email,
    this.location,
    this.notes,
    required this.createdAt,
    this.customerType = 'farmer',
    this.districtName,
  });

  bool get isRetailer => customerType == 'retailer';

  String get typeLabel => isRetailer ? 'Retailer' : 'Farmer';

  factory Customer.fromJson(Map<String, dynamic> json) {
    return Customer(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      phone: json['phone'] ?? '',
      email: json['email'],
      location: json['location'],
      notes: json['notes'],
      createdAt:
          DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
      customerType: json['customer_type']?.toString() ?? 'farmer',
      districtName: json['district_name']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'name': name,
      'phone': phone,
      'email': email,
      'location': location,
      'notes': notes,
      'customer_type': customerType,
    };
  }
}
