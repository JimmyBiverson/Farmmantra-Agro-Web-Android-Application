class SiteSettings {
  final String siteName;
  final String siteTagline;
  final String? logoUrl;
  final String? faviconUrl;
  final String primaryColor;
  final String secondaryColor;
  final String contactEmail;
  final String contactPhone;
  final String address;
  final String currencySymbol;
  final String currencyCode;

  const SiteSettings({
    this.siteName = 'Farmmantra Agro Chemicals',
    this.siteTagline = 'Growing Uganda, One Farm at a Time',
    this.logoUrl,
    this.faviconUrl,
    this.primaryColor = '#2E7D32',
    this.secondaryColor = '#1B5E20',
    this.contactEmail = 'info@farmmantra.co.ug',
    this.contactPhone = '+256 700 000001',
    this.address = 'Kampala, Uganda',
    this.currencySymbol = 'UGX',
    this.currencyCode = 'UGX',
  });

  factory SiteSettings.fromJson(Map<String, dynamic> json) {
    return SiteSettings(
      siteName: json['site_name'] ?? 'Farmmantra Agro Chemicals',
      siteTagline: json['site_tagline'] ?? 'Growing Uganda, One Farm at a Time',
      logoUrl: json['logo_url'],
      faviconUrl: json['favicon_url'],
      primaryColor: json['primary_color'] ?? '#2E7D32',
      secondaryColor: json['secondary_color'] ?? '#1B5E20',
      contactEmail: json['contact_email'] ?? 'info@farmmantra.co.ug',
      contactPhone: json['contact_phone'] ?? '+256 700 000001',
      address: json['address'] ?? 'Kampala, Uganda',
      currencySymbol: json['currency_symbol'] ?? 'UGX',
      currencyCode: json['currency_code'] ?? 'UGX',
    );
  }

  static const SiteSettings defaults = SiteSettings();
}
