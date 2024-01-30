CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Structure of the CSV
 * Maintainers


INTRODUCTION
------------

This module imports user fields from a CSV file and creates a new user
account with the information contained in the file. In addition, it
lets you select which fields to import.

Most fields that comes with the Drupal core can be imported, with the
exception of "Image" and "Taxonomy term".

 * For a full description of the module, visit the [project page][1].
 * To submit bug reports and feature suggestions, or to track changes,
   use the [issue queue][2].
   

REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


RECOMMENDED MODULES
-------------------

* [**Advanced Help**][3]:  
  When this module is enabled, the project's `README.md` will be
  displayed when you visit `/help/ah/user_csv_import/README.md`.


INSTALLATION
------------

Install as you would normally install a contributed Drupal
module. Visit [Installing modules][4] for further information.
   
After the module is installed, rebuild the cache.


CONFIGURATION
-------------

Once you have installed the module, a new button will appear on the
People administration page with the name "+ Import users from CSV".

By clicking on the button you will be redirected to a form where you
can upload the CSV file and configure the following options:

 * The role or set of roles that will be applied to the new users that
   are created. Authenticated user is mandatory.
 * The separation character (i.e. ";" if you're using a French version
   of MS Excel.
 * The default password.
 * The initial status of the imported users.
 * Whether to notify the new user by email.
 * The fields of the User entity that may be filled with the data
   extracted from the CSV file. The fields Name and Email will are
   mandatory.

There is also a checkbox named "Save configuration". Check it to save
the urrent settings when you click "Import users".  This saves you
from having to re-enter them the next time you import users.

Passwords can be set in different ways:

 1. You can set a **Default password** on the import form that will be
    applied to all imported users unless overridden.  You need to
    change this into something only you know, because *all* imported
    accounts will be set up with this password unless it is overridden
    in the CSV. Even if you notify the new user by mail, the account
    will have this password until the user changes it.

 2. If the user is notified by email, the default welcome email will
    send a one time log in url link. This would allow the new users to
    log in to set their own password. To examine the template for the
    welcome email that is send, navigate to **Manage » Configuration »
    People » Account settings** and look up: "Welcome (new user
    created by administrator)".

 3. You may checkmark the **Password** field and provide an unique
    cleartext password in the CSV for each user. It will be encrypted
    when saved in the database. Any unpopulated 'pass' field in the
    CSV-file will be set to the **Default password**.


STRUCTURE OF THE CSV
--------------------

In the first row, each column will contain the machine name of the
field where you want to store the value. In the following rows, but
following the same pattern of columns, the values will be stored.

For example, if the following five fields exist:

* `name`
* `mail`
* `field_first_name`
* `field_last_name`
* `field_phone`

to import two example users with these fields, the CSV-filr may look
like this:

`name,mail,field_first_name,field_last_name,field_phone`  
`john,hohn@example.com,John,Smith,123-123-1234`  
`jane,jane@esample.com,Jane,Doe,123-123-4567`

The fields `name` and `mail` are defined by core. To look up custom
fields in the user profile and their machine names, navigate to
**Manage » Configuration » People » Account settings » Manage fields**.


MAINTAINERS
-----------

 * [Gisle Hannemyr][5]
 * [Marc Fernandez][6]
 * [Ethan Aho][7]


[1]: https://www.drupal.org/project/user_csv_import
[2]: https://www.drupal.org/project/issues/user_csv_import
[3]: https://www.drupal.org/project/advanced_help
[4]: https://www.drupal.org/node/1897420
[5]: https://www.drupal.org/u/gisle
[6]: https://www.drupal.org/u/mcfdez87
[7]: https://www.drupal.org/u/eahonet
