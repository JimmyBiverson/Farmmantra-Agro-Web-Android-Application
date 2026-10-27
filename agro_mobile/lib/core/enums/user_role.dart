enum UserRole {
  franchisePartner('Franchise Partner'),
  farmmantraStaff('Farmmantra Staff'),
  financeDepartment('Finance Department'),
  systemAdministrator('System Administrator'),
  warehouse('Warehouse');

  final String displayName;
  const UserRole(this.displayName);

  bool get isFranchisePartner => this == UserRole.franchisePartner;
  bool get isFarmmantraStaff => this == UserRole.farmmantraStaff;
  bool get isFinanceDepartment => this == UserRole.financeDepartment;
  bool get isSystemAdministrator => this == UserRole.systemAdministrator;
  bool get isWarehouse => this == UserRole.warehouse;

  /// Sales staff only monitor orders; the warehouse (and admin) act on them.
  bool get canManageOrders =>
      this == UserRole.warehouse || this == UserRole.systemAdministrator;

  /// Roles that use the staff (orders / stock) area of the app.
  bool get usesStaffArea =>
      this == UserRole.farmmantraStaff ||
      this == UserRole.warehouse ||
      this == UserRole.systemAdministrator;

  bool get isMobileUser =>
      this == UserRole.franchisePartner ||
      this == UserRole.farmmantraStaff ||
      this == UserRole.financeDepartment ||
      this == UserRole.warehouse;
}
