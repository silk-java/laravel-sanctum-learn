# 目标
1.使用laravel框架进行用户的登录，注册，认证
2.前后端分离的情况下，用户请求接口，使用API token进行认证

# 步骤
## 安装启动
```shell
composer create-project laravel/laravel example-app
cd example-app   
php artisan serve
```
此时，通过访问<code>http://127.0.0.1:8000</code>就可以看到访问成功了

## 安装扩展包
接下来安装laravel官方的扩展包<code>Sanctum</code>，以达到目标
```
composer require laravel/sanctum
```
接下来，你需要使用 vendor:publish Artisan 命令发布 Sanctum 的配置和迁移文件。Sanctum 的配置文件将会保存在 config 文件夹中：

```shell
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```
## 修改配置文件
然后需要修改.env文件文件里面的数据库配置，改为：
```shell
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=caixin
DB_USERNAME=root
DB_PASSWORD=root
```
## 数据库迁移
最后，您应该运行数据库迁移。 Sanctum 将创建一个数据库表来存储 API 令牌：
```shell 
php artisan migrate
```
接下来，如果您想利用 Sanctum 对 SPA 进行身份验证，您应该将 Sanctum 的中间件添加到您应用的 app/Http/Kernel.php 文件中的 api 中间件组中：

```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```
此时查看<code>app/Models/User.php</code>文件，User 模型应使用 Laravel\Sanctum\HasApiTokens trait：

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}
```
## 模拟数据
此时，在数据库中的user表中随便加入一条数据
```sql
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`)
VALUES
	(1, 'java0904', '2954245@qq.com', NULL, '', NULL, NULL, NULL);
```

## 添加访问路由
此时在<code>routes/api.php</code>中配置路由，来获取token
```php

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/tokens/create', function (Request $request) {
    $user = \App\Models\User::find(1);
    ////模拟登陆,此时，会将用户的session存储，但是实际通过API认证的时候，此处用不到
//    \Illuminate\Support\Facades\Auth::login($user);
    $token =$user->createToken($user->name);

    return ['token' => $token->plainTextToken];
})->withoutMiddleware('auth:sanctum');
```
## 测试获取token
此时访问<code>http://127.0.0.1:8000/api/tokens/create</code>，就可以拿到了token

### curl方式
```shell
curl -d '' http://127.0.0.1:8000/api/tokens/create
{"token":"7|ZbSuwu7UBDeQjvXx6iNUCcZJKsbSSO6nctmqLjDq"}
```
### postman测试
![在这里插入图片描述](https://img-blog.csdnimg.cn/13ff8d7b8602481d999305ff7cfd74d3.png)
## 测试其他接口

### 不带token
此时，来访问其他API接口，都需要带上Authorization token才能访问了，否则，会出现如下异常
![在这里插入图片描述](https://img-blog.csdnimg.cn/1226bdffc56149938fc10a6ffcbd577e.png)
### 带上token
此时，把token带上，效果如下

#### curl测试

```shell
curl -H 'Authorization: Bearer 7|ZbSuwu7UBDeQjvXx6iNUCcZJKsbSSO6nctmqLjDq' http://local.app.com/api/user

{"id":1,"name":"java0904","email":"295424581@qq.com","email_verified_at":null,"created_at":null,"updated_at":null}

```

#### postman测试
![在这里插入图片描述](https://img-blog.csdnimg.cn/228ed5ebab7946a7a12488299792e6c4.png)
# 知识点补充1
<code>app/Providers/RouteServiceProvider.php <code>这个文件的作用以及核心代码分析
```php
<?php

class RouteServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            //routes/api.php这个路由文件里面的路由，默认都会使用api中间件，并且路由前缀是/api
            Route::prefix('api')
//                ->middleware(['api'])//这里是默认的中间件，默认只有一个
                //这里我加上了auth:sanctum这个中间件，作为全局使用，就不用为每个路由加上这个中间件了，但是获取token的路由，需要排除这个中间件
                ->middleware(['api','auth:sanctum'])
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            //'routes/web.php'这个文件里面的路由，默认都会使用web这个中间件
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }
}
```
上面的代码提到了两个自带的中间件<code>api</code>和<code>web</code>，他们的定义在<code>app/Http/Kernel.php</code>文件中，它的核心代码如下：
```php
protected $middlewareGroups = [
        //web中间件
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            //这里需要格外注意，所有/route/web.php中的路由，如果是post请求，都会有csrfToken的验证，当然也可以手动给排除一些路由
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        //api中间件
        'api' => [
             \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];
```
注意看<code>web中间件</code>中有<code>            \App\Http\Middleware\VerifyCsrfToken::class,
</code>这行，他的作用是**所有/route/web.php中的路由，如果是post请求，都会有csrfToken的验证，当然也可以手动给排除一些路由**

# 知识点补充2
/route/api.php

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/tokens/create', function (Request $request) {
    $user = \App\Models\User::find(1);
    ////模拟登陆,此时，会将用户的session存储，但是实际通过API认证的时候，此处用不到
    //    \Illuminate\Support\Facades\Auth::login($user);
    $token = $user->createToken($user->name);

    return ['token' => $token->plainTextToken];
})->withoutMiddleware('auth:sanctum');


Route::post('/tokens/create2', function (Request $request) {
    //这里可以写自己的一些验证逻辑
    //用户来获取token，必须携带用户名和密码
    $password = $request->get("password");
    $username = $request->get("username");
    $user = \App\Models\User::where('password', $password)->where('username', $username)->first();
    if (!$user) {
        return [
            'code' => 500,
            'msg' => '用户名密码错误'
        ];
    }
    $token = $user->createToken($user->name);
    return ['token' => $token->plainTextToken];
})->withoutMiddleware('auth:sanctum');

//用来写使用session，不是前后端分离的用户登陆
Route::post('/login', function (Request $request) {
    //laravel内部的验证方式
    if (\Illuminate\Support\Facades\Auth::attempt([
        'username' => $request->get("name"),
        'password' => $request->get("password")])) {
        //登陆成功
        //保存session
    } else {
        //登陆失败
    }
})->withoutMiddleware('auth:sanctum');

```

## 代码仓库
[https://github.com/silk-java/laravel-sanctum-learn](https://github.com/silk-java/laravel-sanctum-learn)
