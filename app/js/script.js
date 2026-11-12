const header2 = document.querySelector('.header .header-2');
const navbar = header2.querySelector('.navbar');
const userBox = header2.querySelector('.usuario-caja');

document.querySelector('#user-btn').onclick = () => {
   userBox.classList.toggle('active');
   navbar.classList.remove('active');
};

document.querySelector('#menu-btn').onclick = () => {
   navbar.classList.toggle('active');
   userBox.classList.remove('active');
};

window.onscroll = () => {
   userBox.classList.remove('active');
   navbar.classList.remove('active');
   header2.classList.toggle('active', window.scrollY > 60);
};
