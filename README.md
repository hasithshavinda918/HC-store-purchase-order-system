# 🛒 HC Store - Purchase Order Management System

A comprehensive, multilingual Purchase Order Management System built with PHP, MySQL, and modern web technologies.

## 🌟 Features

### 🔧 Core Functionality
- **Purchase Order Management**: Create, edit, view, and track purchase orders
- **Supplier Management**: Complete supplier database with contact information
- **Inventory Management**: Product catalog with stock level tracking
- **Stock Movements**: Detailed stock in/out tracking with user attribution
- **User Management**: Role-based access control (Admin/Staff)

### 📊 Advanced Reporting
- **Stock Movements Report**: Detailed movement history with filtering
- **Inventory Value Report**: Complete inventory valuation analysis  
- **Low Stock Report**: Items needing restocking with recommendations
- **Out of Stock Report**: Zero-stock items with usage analysis
- **User Activity Report**: User performance and activity tracking
- **Export Functionality**: CSV downloads for all reports

### 🌍 Multilingual Support
- **English**: Full English interface
- **Sinhala (සිංහල)**: Complete Sinhala translations
- **Tamil (தமிழ்)**: Full Tamil language support
- **Dynamic Language Switching**: Real-time language changes
- **Persistent Language Selection**: Remembers user language preference

### 🎨 Modern UI/UX
- **Responsive Design**: Works on all devices (desktop, tablet, mobile)
- **Tailwind CSS**: Modern, clean interface design
- **Interactive Elements**: Dynamic forms, filtering, pagination
- **Print Support**: Printer-friendly reports and documents
- **Font Awesome Icons**: Professional iconography throughout

## 🛠️ Technology Stack

- **Backend**: PHP 8.4+
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **CSS Framework**: Tailwind CSS
- **Icons**: Font Awesome 6
- **Server**: Apache (XAMPP)

## 📋 System Requirements

- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher / MariaDB 10.2+
- **Apache**: 2.4+
- **Web Browser**: Modern browser with JavaScript enabled

## 🚀 Installation Guide

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/hc-store-po-management.git
cd hc-store-po-management
```

### 2. Database Setup
1. Start your XAMPP/WAMP server
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Create a new database named `hc_store_stock`
4. Import the database schema:
   ```sql
   # Run the following SQL commands in phpMyAdmin or MySQL command line
   # (Database schema will be provided in /database/schema.sql)
   ```

### 3. Configuration
1. Copy the project files to your web server directory (e.g., `C:\xampp\htdocs\`)
2. Update database connection settings in `includes/db_connection.php`:
   ```php
   $host = 'localhost';
   $username = 'root';
   $password = '';
   $database = 'hc_store_stock';
   ```

### 4. Access the System
- Open your web browser
- Navigate to `http://localhost/project3/`
- Default login credentials:
  - **Username**: admin
  - **Password**: admin123

## 📁 Project Structure

```
hc-store-po-management/
├── admin/                      # Admin dashboard
├── categories/                 # Category management
├── includes/                   # Shared PHP files
│   ├── languages/             # Translation files
│   │   ├── en.php            # English translations
│   │   ├── si.php            # Sinhala translations
│   │   └── ta.php            # Tamil translations
│   ├── auth.php              # Authentication functions
│   ├── db_connection.php     # Database connection
│   ├── language.php          # Language management
│   ├── header.php           # Common header
│   ├── footer.php           # Common footer
│   └── admin_navbar.php     # Navigation bar
├── products/                  # Product management
├── purchase_orders/           # Purchase order management
├── reports/                   # Reporting system
│   ├── index.php            # Reports dashboard
│   ├── movements.php        # Stock movements report
│   ├── inventory-value.php  # Inventory valuation
│   ├── out-of-stock.php     # Out of stock report
│   ├── user-activity.php    # User activity report
│   └── export.php           # Data export functionality
├── suppliers/                 # Supplier management
├── users/                     # User management
├── language_demo.php          # Language system demo
├── language_test.php          # Language testing page
└── index.php                 # Main entry point
```

## 🔐 User Roles & Permissions

### Administrator
- Full system access
- User management
- System configuration
- All reports and analytics
- Data export capabilities

### Staff
- Purchase order creation/editing
- Inventory management
- Supplier information access
- Basic reporting

## 🌍 Language Support

The system supports three languages with complete translations:

- **English**: Default system language
- **Sinhala**: Complete Sri Lankan Sinhala support
- **Tamil**: Full Tamil language interface

### Language Features:
- 200+ translation keys per language
- Real-time language switching
- Browser language detection
- Session-persistent language selection
- JavaScript integration for dynamic content

## 📊 Reporting Features

### Stock Movements Report
- Detailed movement history with filtering
- Date range selection
- Product/user/type filtering
- Pagination support
- CSV export

### Inventory Value Report
- Complete inventory valuation
- Category breakdown
- Top valuable products
- Stock status indicators
- Total value calculations

### Out of Stock Report
- Zero-stock item identification
- Usage pattern analysis
- Supplier recommendations
- Action buttons for quick restocking

### User Activity Report
- User performance tracking
- Movement statistics per user
- Activity timeline
- Performance insights

## 🔧 Development

### Adding New Languages
1. Create a new language file in `includes/languages/`
2. Copy the structure from `en.php`
3. Translate all keys to the new language
4. Add the language option to the language selector

### Extending Reports
1. Create new report file in `reports/` directory
2. Follow existing report structure
3. Add translation keys to language files
4. Update navigation and export functionality

### Database Schema
The system uses the following main tables:
- `users` - User authentication and profiles
- `products` - Product catalog
- `categories` - Product categorization
- `suppliers` - Supplier information
- `purchase_orders` - PO headers
- `purchase_order_items` - PO line items
- `stock_movements` - Inventory tracking

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Your Name**
- GitHub: [@hasithshavinda918](https://github.com/hasithshavinda918)
- Email: hasithshavinda918@gmail.com

  
## 🙏 Acknowledgments

- Built with modern web technologies
- Inspired by real-world business requirements
- Community feedback and contributions
- Open source libraries and frameworks

## 🔄 Version History

- **v1.0.0** - Initial release with core functionality
- **v1.1.0** - Added multilingual support (English, Sinhala, Tamil)
- **v1.2.0** - Enhanced reporting system with advanced analytics
- **v1.3.0** - Improved UI/UX with responsive design

## 📞 Support

For support, email your.email@example.com or create an issue in this repository.

---

**Built with ❤️ for efficient business management**
