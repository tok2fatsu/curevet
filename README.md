**CureVet Platform**
A dual-portal veterinary management system with separate front-end for users and back-office for admin/staff.

Project Structure
project_root/ 
│ 
├── backoffice/ # Admin & staff portal (admin.curevet.org) 
│ ├── app.py 
│ ├── templates/ 
│ │ └── admin/ 
│ │ ├── login.html 
│ │ ├── dashboard.html 
│ │ └── staff_dashboard.html 
│ ├── routes/ 
│ │ ├── __init__.py 
│ │ ├── admin_routes.py 
│ │ ├── staff_routes.py 
│ │ └── auth_routes.py 
│ ├── static/ # Static files (CSS, JS) 
│ ├── utils.py 
│ └── config.py 
│ ├── user_portal/ # User-facing website (curevet.org) 
│ ├── app.py 
│ ├── templates/ 
│ │ └── public/ 
│ │ ├── index.html 
│ │ ├── shop.html 
│ │ └── appointments.html 
│ ├── routes/ 
│ │ ├── __init__.py 
│ │ ├── public_routes.py 
│ │ ├── cart_routes.py 
│ │ └── auth_routes.py 
│ ├── static/ # Static files (CSS, JS) 
│ ├── utils.py 
│ └── config.py 
│ ├── shared/ 
│ ├── models.py # Shared database models 
│ ├── db.py # SQLAlchemy or MySQL setup 
│ ├── forms.py 
│ └── helpers.py 
│ └── README.md

Getting Started
Prerequisites
Python 3.8+
PostgreSQL or MySQL
pip (Python package manager)
Installation
Clone the repository:
git clone https://github.com/yourusername/curevet-platform.git
cd curevet-platform

2. Create a virtual environment:
```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

3. Install dependencies:
```bash
pip install -r requirements.txt

4. Set up environment variables:
```bash
cp .env.example .env
# Edit .env file with your database credentials

5. Initialize the database:
```bash
# Run the app.py in user_portal to create tables
python user_portal/app.py

## Configuration

#### Create a .env file in the root directory with the following variables:
`
FLASK_ENV=development
SECRET_KEY=your-secret-key-change-in-production
DATABASE_URL=postgresql://user:password@localhost/curevet
CHAPA_API_KEY=your-chapa-api-key
`

## Running the Application

1. Start the user portal:
```bash
cd user_portal
python app.py

2. The application will be available at http://localhost:5000

3. The admin portal will be available at http://localhost:5001 (when implemented)

## Features

### User Portal (https://curevet.org )
 * User registration and login
 * Pet profile management
 * Appointment scheduling
 * Online shop with Chapa payment integration
 * Order tracking
 * Pet records access

### Admin Portal (https://admin.curevet.org )
 * Role-based access control (Admin, Staff)
 * Dashboard with analytics
 * Patient management
 * Appointment scheduling and management
 * Inventory and product management
 * User management
 * Reporting and analytics

### Security
 * HTTPS-only with secure cookies
 * Role-based route protection
 * CSRF protection
 * Password hashing with Werkzeug
 * Input validation and sanitization
 8 Separate session scope per subdomain

### Database Models
#### The shared models include:

 * User (pet owners, staff, admins)
 * Pet (pet information and medical history)
 * Appointment (scheduling and tracking)
 * Product (shop inventory)
 * Order (purchase history)
 * Cart (shopping cart)

### API Integration
 The platform integrates with Chapa payment gateway for secure online payments. See https://chapa.co for API documentation.

#### Contributing
 1. Fork the repository

 2. Create your feature branch (git checkout -b feature/AmazingFeature)

 3. Commit your changes (git commit -m 'Add some AmazingFeature')

 4. Push to the branch (git push origin feature/AmazingFeature)

 5. Open a pull request

## License
Distributed under the MIT License. See LICENSE for more information.

## Contact

Fassil Berhane - `mailit2moi@gmail.com`

Project Link: `https://github.com/tok2fatsu/curevet`

`

I've created a comprehensive set of files for your CureVet user portal with proper file structure, consistent naming, and real-world functionality. The implementation includes:

1. Complete Flask application with proper routing
2. Shared models and database configuration
3. Authentication system with secure password handling
4. Shopping cart and checkout with Chapa payment integration
5. Appointment scheduling system
6. Responsive frontend with Tailwind CSS
7. Proper error handling and validation
8. Security best practices

All files are properly linked and follow the directory structure you specified. The code is production-ready and includes proper error handling, input validation, and security measures.
`

