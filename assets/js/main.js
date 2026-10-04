function showCart(){
    const popup=document.getElementById("cartPopup");
    if(popup){
        popup.style.display="flex";
    }
}

function showLogin(){
    const popup=document.getElementById("loginPopup");
    if(popup){
        popup.style.display="flex";
    }
}

function showSignup(){
    const popup=document.getElementById("signupPopup");
    if(popup){
        popup.style.display="flex";
    }
}

function closePopup(id){
    const popup=document.getElementById(id);
    if(popup){
        popup.style.display="none";
    }
}

function showProfileMenu(){
    const sidebar=document.getElementById("profileSidebar");
    const overlay=document.getElementById("profileOverlay");

    if(sidebar){
        sidebar.classList.add("show");
    }

    if(overlay){
        overlay.classList.add("show");
    }
}

function closeProfileMenu(){
    const sidebar=document.getElementById("profileSidebar");
    const overlay=document.getElementById("profileOverlay");

    if(sidebar){
        sidebar.classList.remove("show");
    }

    if(overlay){
        overlay.classList.remove("show");
    }
}

function showProfilePage(){
    closeProfileMenu();

    const page=document.getElementById("profilePage");

    if(page){
        page.style.display="flex";
    }
}

function showSettingsPage(){
    closeProfileMenu();

    const page=document.getElementById("settingsPage");

    if(page){
        page.style.display="flex";
    }
}

function closeAccountPage(id){
    const page=document.getElementById(id);

    if(page){
        page.style.display="none";
    }
}

document.addEventListener("DOMContentLoaded",function(){

    const hearts=document.querySelectorAll(".heart");

    hearts.forEach(function(button){
        button.addEventListener("click",function(){
            button.classList.toggle("active");

            if(button.classList.contains("active")){
                button.textContent="♥";
            }else{
                button.textContent="♡";
            }
        });
    });

    const filterButtons=
        document.querySelectorAll(".filter-button");

    const products=
        document.querySelectorAll(".products-grid .product");

    filterButtons.forEach(function(button){
        button.addEventListener("click",function(){
            filterButtons.forEach(function(item){
                item.classList.remove("active");
            });

            button.classList.add("active");

            const category=button.dataset.category;

            products.forEach(function(product){
                if(category==="All"||
                   product.dataset.category===category){
                    product.style.display="";
                }else{
                    product.style.display="none";
                }
            });
        });
    });

    const sortProducts=
        document.getElementById("sortProducts");

    if(sortProducts){
        sortProducts.addEventListener("change",function(){

            const productList=
                document.getElementById("productList");

            if(!productList){
                return;
            }

            const productItems=
                Array.from(productList.children);

            if(sortProducts.value==="lowest"){
                productItems.sort(function(a,b){
                    return Number(a.dataset.price)-
                           Number(b.dataset.price);
                });
            }else if(sortProducts.value==="highest"){
                productItems.sort(function(a,b){
                    return Number(b.dataset.price)-
                           Number(a.dataset.price);
                });
            }else if(sortProducts.value==="oldest"){
                productItems.sort(function(a,b){
                    return Number(a.dataset.order)-
                           Number(b.dataset.order);
                });
            }else{
                productItems.sort(function(a,b){
                    return Number(b.dataset.order)-
                           Number(a.dataset.order);
                });
            }

            productItems.forEach(function(product){
                productList.appendChild(product);
            });
        });
    }

    const orderFilter=
        document.getElementById("orderFilter");

    if(orderFilter){
        orderFilter.addEventListener("change",function(){

            const orders=
                document.querySelectorAll(".order-card");

            orders.forEach(function(order){
                if(orderFilter.value==="All"||
                   order.dataset.status===orderFilter.value){
                    order.style.display="flex";
                }else{
                    order.style.display="none";
                }
            });
        });
    }

    const cartButtons=
        document.querySelectorAll(".product-bottom button");

    cartButtons.forEach(function(button){
        button.addEventListener("click",function(){

            const product=
                button.closest(".product");

            if(!product){
                return;
            }

            const name=
                product.querySelector("h3").textContent;

            const message=
                document.getElementById("cartMessage");

            if(message){
                message.textContent=
                    name+" has been added to your cart.";
            }

            showCart();
        });
    });

    const signupForm=
        document.getElementById("signupForm");

    if(signupForm){
        signupForm.addEventListener("submit",function(event){

            event.preventDefault();

            const fullname=
                document.getElementById("signupFullname");

            const username=
                document.getElementById("signupUsername");

            const email=
                document.getElementById("signupEmail");

            const password=
                document.getElementById("signupPassword");

            const confirm=
                document.getElementById("confirmPassword");

            const message=
                document.getElementById("signupMessage");

            message.textContent="";

            const fullnameValue=
                fullname.value.trim();

            const usernameValue=
                username.value.trim();

            const emailValue=
                email.value.trim();

            const passwordValue=
                password.value;

            const confirmValue=
                confirm.value;

            if(fullnameValue===""){
                message.textContent=
                    "Please enter your full name.";
                fullname.focus();
                return;
            }

            if(usernameValue===""){
                message.textContent=
                    "Please enter a username.";
                username.focus();
                return;
            }

            if(!emailValue.includes("@")||
               !emailValue.includes(".")){
                message.textContent=
                    "Please use valid email";
                email.focus();
                return;
            }

            const hasLength=
                passwordValue.length>=12;

            const hasUpper=
                /[A-Z]/.test(passwordValue);

            const hasLower=
                /[a-z]/.test(passwordValue);

            const hasNumber=
                /[0-9]/.test(passwordValue);

            const hasSpecial=
                /[^A-Za-z0-9]/.test(passwordValue);

            if(!hasLength||
               !hasUpper||
               !hasLower||
               !hasNumber||
               !hasSpecial){

                message.textContent=
                    "Password does not meet the requirements.";

                password.focus();
                return;
            }

            if(passwordValue!==confirmValue){
                message.textContent=
                    "Passwords do not match.";
                confirm.focus();
                return;
            }

            signupForm.submit();
        });
    }

    const loginPopup=
        document.getElementById("loginPopup");

    if(loginPopup&&
       window.location.search.includes("login=")){
        loginPopup.style.display="flex";
    }

    const signupPopup=
        document.getElementById("signupPopup");

    if(signupPopup&&
       window.location.search.includes("signup=")){
        signupPopup.style.display="flex";
    }
});