# DirectAdminLaravel
## Class for adding, modifying and deleting e-mails to the Directadmin control panel for Laravel
## How to use
### Before starting, you must add the following lines in the your TestController.php
```
use App\CustomClass\DirectAdmin;
use App\CustomClass\DA_Api;
use App\CustomClass\DA_Emails;
use App\Email;
```
### Create New Email
```
$socket = new DirectAdmin;
$socket->connect('domain.com', 2222);
$socket->set_login('userlogin', 'password');
DA_Api::$DEFAULT_SOCKET = $socket;
DA_Api::$DEFAULT_DOMAIN = 'domain.com';
// new DA_Emails instance
$emails = new DA_Emails;
$emails->create('test@domain.com', '123456', 100);
```
#### create method Containing email and password and quota, if it quota 0 this mean unlimited
### Delete Email
```
$socket = new DirectAdmin;
$socket->connect('domain.com', 2222);
$socket->set_login('userlogin', 'password');
DA_Api::$DEFAULT_SOCKET = $socket;
DA_Api::$DEFAULT_DOMAIN = 'domain.com';
// new DA_Emails instance
$emails = new DA_Emails;
$emails->delete('test@domain.com');
```
### Email Modify
```
$socket = new DirectAdmin;
$socket->connect('domain.com', 2222);
$socket->set_login('userlogin', 'password');
DA_Api::$DEFAULT_SOCKET = $socket;
DA_Api::$DEFAULT_DOMAIN = 'domain.com';
// new DA_Emails instance
$emails = new DA_Emails;
$emails->modify('test@domain.com', 'newpassword', 100);
```
### Other methods
#### fetche the data of one user
`$emails->fetch();`
#### fetches the data, quota and usage, of one user
`$emails->fetchUserQuota('testing');`
#### fetches all data, including quotas and usage
`$emails->fetchQuotas();`
