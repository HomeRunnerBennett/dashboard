# Transaction Analytics Dashboard

A comprehensive real-time monitoring dashboard for tracking NPMS and MALPAY transactions with visual analytics and connection status monitoring.

![Dashboard Preview](img/logo.jpg)

## 📋 Overview

This PHP-based web application provides a real-time dashboard for monitoring transaction data from two different systems:
- **NPMS** (Network Payment Management System) - Wallet analytics
- **MALPAY** - Transaction analytics

The dashboard features interactive charts, connection status monitoring, and automatic tab switching for comprehensive data visualization.

## ✨ Features

- **Real-time Monitoring**: Live tracking of transaction data from both NPMS and MALPAY systems
- **Connection Status**: Visual indicators showing database connection status for both systems
- **Interactive Charts**: Beautiful visualizations using Chart.js for data analysis
- **Responsive Design**: Mobile-friendly interface that works on all devices
- **Auto-Switching Tabs**: Automatic tab rotation between NPMS and MALPAY analytics
- **Modern UI**: Clean, professional interface with status badges and intuitive controls

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, JavaScript
- **Charts**: Chart.js
- **Database**: MySQL
- **Styling**: Custom CSS with responsive design

## 📁 Project Structure

```
transaction-dashboard/
├── config.php              # Database configuration
├── header.php              # Main header and connection testing
├── index.php               # Main dashboard page
├── css/
│   └── style.css           # Main stylesheet
├── img/
│   ├── favicon.ico         # Site favicon
│   └── logo.jpg            # Project logo
├── js/
│   └── script.js           # Dashboard functionality
└── README.md               # This file
```

## 🚀 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/transaction-dashboard.git
   cd transaction-dashboard
   ```

2. **Configure Database**
   - Create databases for NPMS and MALPAY systems
   - Update `config.php` with your database credentials:
   ```php
   class Config {
       public static $npms_config = [
           'host' => 'your_npms_host',
           'dbname' => 'your_npms_database',
           'username' => 'your_username',
           'password' => 'your_password'
       ];
       
       public static $malpay_config = [
           'host' => 'your_malpay_host',
           'dbname' => 'your_malpay_database',
           'username' => 'your_username',
           'password' => 'your_password'
       ];
   }
   ```

3. **Set File Permissions**
   ```bash
   chmod 755 css/ img/ js/
   chmod 644 *.php css/* img/* js/*
   ```

4. **Access the Dashboard**
   - Navigate to `http://your-domain.com/transaction-dashboard/`

## ⚙️ Configuration

### Database Connections
The dashboard supports two independent database connections:
- **NPMS Configuration**: Wallet transaction data
- **MALPAY Configuration**: Payment transaction data

### Customization
- Modify `css/style.css` for styling changes
- Update chart configurations in the JavaScript files
- Adjust auto-switch timer in the tab functionality

## 🎯 Usage

1. **Dashboard Overview**: The main page loads with MALPAY analytics by default
2. **Connection Status**: Check the status badges for NPMS and MALPAY connections
3. **Tab Navigation**: Switch between NPMS and MALPAY analytics using tabs
4. **Auto-Switch**: Tabs automatically rotate every 5 minutes for comprehensive monitoring
5. **Real-time Updates**: Data refreshes automatically at configured intervals

## 📊 Features in Detail

### Connection Monitoring
- Real-time database connection testing
- Visual status indicators (Connected/Disconnected)
- Error handling for connection failures

### Data Visualization
- Transaction volume charts
- Revenue analytics
- User activity metrics
- Comparative analysis between systems

### Responsive Design
- Optimized for desktop, tablet, and mobile devices
- Flexible layout adapts to screen size
- Touch-friendly interface elements

## 🔧 Troubleshooting

### Common Issues

1. **Connection Errors**
   - Verify database credentials in `config.php`
   - Check database server accessibility
   - Ensure proper firewall rules

2. **Favicon Not Displaying**
   - Clear browser cache
   - Verify file path in `header.php`
   - Check file permissions for `img/favicon.ico`

3. **Charts Not Loading**
   - Check JavaScript console for errors
   - Verify Chart.js CDN availability
   - Ensure data endpoints are accessible

### Debug Mode
Enable debug mode by setting appropriate flags in the configuration to see detailed error messages.

## 🤝 Contributing

We welcome contributions! Please feel free to submit pull requests or open issues for bugs and feature requests.

### Contribution Guidelines
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

If you encounter any problems or have questions:

1. Check the [Issues](https://github.com/yourusername/transaction-dashboard/issues) page
2. Create a new issue with detailed description
3. Contact the development team

## 🙏 Acknowledgments

- Chart.js for powerful charting capabilities
- PHP community for robust backend support
- Contributors and testers

---

**Note**: This dashboard is designed for internal monitoring purposes. Ensure proper security measures are implemented when deploying in production environments.

For more information, visit the [project wiki](https://github.com/yourusername/transaction-dashboard/wiki) or contact the development team.
