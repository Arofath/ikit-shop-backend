ខាងក្រោមនេះគឺជា ផែនការមេ (Master Plan) ទាំង ៦ ជំហាន ដែលយើងត្រូវធ្វើការកែប្រែទាំងផ្នែក Backend និង Frontend៖


ទី១៖ ផ្នែក Backend (Laravel Database & Package)
ដំឡើង Package: ដំឡើង spatie/laravel-permission តាមរយៈ Composer ។

Setup Database: Run Migration របស់ Spatie ដើម្បីបង្កើតតារាងថ្មីចំនួន ៣ សំខាន់ៗគឺ roles, permissions, និង role_has_permissions (តារាងទាំងនេះដំណើរការដោយស្វ័យប្រវត្តិជាមួយតារាង users របស់អ្នក)។

កែប្រែ Model: បន្ថែម Trait HasRoles ទៅក្នុង User.php Model ដើម្បីឱ្យ User អាចប្រើប្រាស់មុខងារ $user->assignRole() ឬ $user->hasPermissionTo() បាន។

ទី២៖ កំណត់សិទ្ធិមូលដ្ឋាន (Role & Permission Seeding)
យើងត្រូវបង្កើត Seeder មួយដើម្បីបញ្ចូលទិន្នន័យ (Default Roles & Permissions) ទៅក្នុង Database ជាមុន៖
Permissions (សិទ្ធិលម្អិត): create-product, edit-product, delete-product, view-orders, manage-users, ជាដើម។
Roles (តួនាទី និងការផ្តល់សិទ្ធិ):
super_admin: មានសិទ្ធិគ្រប់យ៉ាង (Bypass គ្រប់ Permissions)។
admin: អាចធ្វើបានស្ទើរតែទាំងអស់ តែមិនអាចលុប Admin គ្នាឯង ឬដូរ System Settings បាន។
sale_staff: អាចត្រឹមមើលផលិតផល, ទម្លាក់ស្តុក, និងមើល/ប្តូរស្ថានភាព Order (មិនអាចលុប Product បានទេ)។
customer: ប្រើប្រាស់សិទ្ធិតាម App ធម្មតា (ទិញទំនិញ មើលប្រវត្តិទិញ)។

ទី៣៖ ការការពារ API Routes (Backend Middleware)
យើងត្រូវចាក់សោ Route របស់ API នៅក្នុង api.php និង Controllers មិនឱ្យអ្នកគ្មានសិទ្ធិហៅ API បាន៖

ប្រើ Middleware: Route::middleware(['role:admin|sale_staff', 'permission:view-orders'])

ប្រើ Policy (Optional): សម្រាប់លក្ខខណ្ឌស្មុគស្មាញ (ឧទាហរណ៍ Sale Staff អាចកែបានតែ Order ដែលខ្លួនឯងជាអ្នកកាន់)។

ទី៤៖ កែប្រែ Login API សម្រាប់ Frontend
នៅពេល User ធ្វើការ Login ជោគជ័យ API ត្រូវតែបញ្ជូនបញ្ជី Roles និង Permissions របស់ User នោះទៅឱ្យ Vue Frontend វិញ ដើម្បីឱ្យ Vue ដឹងថា User ម្នាក់នេះអាចធ្វើអ្វីបានខ្លះ។

ទី៥៖ ផ្នែក Frontend State & Router Guards (Vue + Pinia)
Pinia Store: រក្សាទុកបញ្ជី roles និង permissions ទៅក្នុង authStore.js ។

Vue Router (beforeEach): កំណត់ច្បាប់នៅក្នុង Route នីមួយៗ (Route Meta)។ ឧទាហរណ៍៖ ប្រសិនបើ Sale Staff ព្យាយាមវាយ URL admin/users (ទំព័រគ្រប់គ្រងបុគ្គលិក) Router នឹងទាត់គាត់ចេញទៅកាន់ទំព័រ Dashboard វិញ ឬបង្ហាញទំព័រ 403 Forbidden ព្រោះគាត់គ្មានសិទ្ធិ។

ទី៦៖ ការលាក់/បង្ហាញ ប៊ូតុងនៅលើ UI (Vue Components)
យើងត្រូវបង្កើត Custom Directive មួយនៅក្នុង Vue (ឧទាហរណ៍ v-permission="'delete-product'") ឬប្រើ v-if ដើម្បីលាក់ប៊ូតុង។

ឧទាហរណ៍៖ ប៊ូតុង "Delete Product" នឹងបង្ហាញតែសម្រាប់ admin និង super_admin ប៉ុណ្ណោះ។ បើ sale_staff ចូលមើល គាត់នឹងមិនឃើញប៊ូតុងលុបនេះទេ ឃើញតែប៊ូតុង "Edit" ប៉ុណ្ណោះ។

------------------------------------------------------------------

=> តារាងទាំង ៥ ដែលត្រូវបានបង្កើតឡើងមាន៖តាមរយៈកូដនេះ ប្រព័ន្ធនឹងបង្កើតតារាងថ្មីចំនួន ៥ ចូលទៅក្នុង Database របស់អ្នក៖  
1. permissions: តារាងនេះប្រើសម្រាប់ផ្ទុកសិទ្ធិលម្អិតនីមួយៗនៅក្នុងប្រព័ន្ធ (ឧទាហរណ៍៖ ទិន្នន័យដូចជា create-product, edit-order, delete-user ជាដើម)។  
2. roles: តារាងនេះប្រើសម្រាប់ផ្ទុកឈ្មោះតួនាទីធំៗ (ឧទាហរណ៍៖ super_admin, admin, sale_staff, customer)។  
3. model_has_permissions: នេះគឺជាតារាងភ្ជាប់ (Pivot Table) ដែលប្រើសម្រាប់ភ្ជាប់សិទ្ធិ (Permission) ទៅឱ្យ User ណាម្នាក់ដោយផ្ទាល់ ក្នុងករណីដែលអ្នកមិនចង់ផ្តល់សិទ្ធិតាមរយៈ Role។  
3. model_has_roles: តារាងភ្ជាប់នេះមានតួនាទីប្រាប់ប្រព័ន្ធថា តើ User (ឬ Model) ម្នាក់ៗកំពុងកាន់តួនាទី (Role) អ្វីខ្លះ។  
4. role_has_permissions: ជាតារាងភ្ជាប់សម្រាប់កំណត់ព្រំដែនថា តួនាទី (Role) នីមួយៗត្រូវបានអនុញ្ញាតឱ្យធ្វើអ្វីខ្លះ (Permissions)។ ឧទាហរណ៍៖ ភ្ជាប់ Role admin ទៅនឹង Permission edit-product។  

----------------------------------------------------------
អ្វីដែលគួរ បិទ/លាក់ (Hide) ពី Sale Staff
មានចំណុចរសើបមួយចំនួនដែលបុគ្គលិកលក់មិនគួរមានសិទ្ធិមើលឃើញ ឬកែប្រែឡើយ៖

ប៊ូតុង "Add Product":

មូលហេតុ: ការបន្ថែមទំនិញថ្មីចូលប្រព័ន្ធ គឺជាការងាររបស់ Admin ឬអ្នកគ្រប់គ្រងស្តុក។ Sale Staff គួរតែមានសិទ្ធិត្រឹមតែលក់ទំនិញដែលមានស្រាប់ប៉ុណ្ណោះ។

ជួរឈរ "COST PRICE" (តម្លៃដើម):

មូលហេតុ (សំខាន់បំផុត): នេះជាទិន្នន័យសម្ងាត់ពាណិជ្ជកម្ម។ បុគ្គលិកលក់ត្រូវការដឹងត្រឹមតែ តម្លៃលក់ចេញ (Regular/Final Price) ដើម្បីប្រាប់អតិថិជនប៉ុណ្ណោះ។ ការឱ្យគាត់ដឹងពីតម្លៃដើម គឺស្មើនឹងប្រាប់គាត់ពីប្រាក់ចំណេញ (Profit Margin) របស់ហាង ដែលអាចបង្កជាបញ្ហានៅពេលគាត់ចរចាតម្លៃជាមួយភ្ញៀវ ឬលេចធ្លាយព័ត៌មានទៅគូប្រជែង។

ប៊ូតុងបិទបើក "RECOMMENDED" (Toggle Switch):

មូលហេតុ: ការកំណត់ថាទំនិញណាគួរ Recommended លើ Website ជាយុទ្ធសាស្ត្រទីផ្សារ (Marketing) របស់ Admin។ យើងគួរតែ Disable ប៊ូតុងនេះមិនឱ្យគាត់ចុចបាន ឬលាក់ជួរឈរនេះតែម្តង។

សកម្មភាព "Edit" និង "Delete" ក្នុង Actions ទាំង ៣ (3-dots):

មូលហេតុ: គាត់មិនគួរមានសិទ្ធិកែប្រែឈ្មោះទំនិញ ឬលុបទំនិញចោលឡើយ។ គាត់គួរតែមានសិទ្ធិត្រឹមតែ "View" មើលព័ត៌មានលម្អិតប៉ុណ្ណោះ។