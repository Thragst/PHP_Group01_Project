<?php
session_start();

$users=[
    'customer'=>[
        'username'=>'customer',
        'password'=>'Customer@12345',
        'name'=>'Customer',
        'user_id'=>'SM-CUST-001',
        'role'=>'Customer',
        'expiry'=>'2026-12-31'
    ],
    'admin'=>[
        'username'=>'admin',
        'password'=>'Admin@123456',
        'name'=>'Admin',
        'user_id'=>'SM-ADM-001',
        'role'=>'Admin',
        'expiry'=>'2026-12-31'
    ]
];

if(isset($_SESSION['users'])){
    foreach($_SESSION['users'] as $user){
        $users[$user['username']]=[
            'username'=>$user['username'],
            'password'=>$user['password'],
            'name'=>$user['name'],
            'user_id'=>$user['user_id'],
            'role'=>$user['role'],
            'expiry'=>$user['expiry']??'2026-12-31',
            'registered'=>true
        ];
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $username=$_POST['username']??'';
    $password=$_POST['password']??'';

    if(isset($users[$username])){
        $user=$users[$username];
        $passwordCorrect=false;

        if(isset($user['registered'])&&
           $user['registered']===true){

            $passwordCorrect=password_verify(
                $password,
                $user['password']
            );
        }else{
            $passwordCorrect=$password===$user['password'];
        }

        if($passwordCorrect){
            if(strtotime(date('Y-m-d'))>
               strtotime($user['expiry'])){

                header(
                    'Location:index.php?login=expired'
                );
                exit;
            }

            $_SESSION['logged_in']=true;
            $_SESSION['user_name']=$user['name'];
            $_SESSION['username']=$user['username'];
            $_SESSION['user_id']=$user['user_id'];
            $_SESSION['user_role']=$user['role'];

            if($user['role']==='Admin'){
                header('Location:admin/index.php');
            }else{
                header('Location:index.php');
            }

            exit;
        }
    }

    header('Location:index.php?login=error');
    exit;
}

header('Location:index.php');
exit;
?>