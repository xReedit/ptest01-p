const paramsSwalAlert = {
    icon: '',    
    title: '',
    html: '',
    text: '',        
    showCancelButton: false,
    confirmButtonText: 'Listo',
    cancelButtonText: 'Cancelar'
}

const backgroundAlertSwal = {
    background: '#212121',
    color: 'white'
};

const backgroundAlertSwalBorderless = {
    background: 'rgba(54, 70, 93, 0.99)',
    color: 'white'
};

const colorBtnAlertSwal = {
    confirmButtonColor: '#4285F4'
}



const ToastAlertSwal = Swal.mixin({
    position: 'bottom-end', 
    toast: true,                
    showConfirmButton: false,
    timer: 1500,
    background: backgroundAlertSwal.background,
    color: backgroundAlertSwal.color,
    showClass: {
        popup: 'animate__animated animate__bounceIn'
    }    
    // hideClass: {
    //   popup: 'animate__animated animate__bounceOut'
    // }    
  });

const themeOneAlertSwal = Swal.mixin({
    confirmButtonColor: colorBtnAlertSwal.confirmButtonColor,
    background: backgroundAlertSwalBorderless.background,
    color: backgroundAlertSwalBorderless.color,    
});

const themeDefaultAlertSwal = Swal.mixin({
    confirmButtonColor: colorBtnAlertSwal.confirmButtonColor,
});

function showToastSwal(icon, title) {
    ToastAlertSwal.fire({
        icon: icon,
        title: title
      })
}

function showAlertSwalOk(icon, title, text, btnOkText = 'Ok', theme = 1) {
    const _themeShowSwalAlert = returnThemeSwalAlert(theme);
    _themeShowSwalAlert.fire({
        title: title,
        text: text,
        icon: icon,
        confirmButtonText: btnOkText,
    })
}

// theme 0 = defaul 1 0 
function showAlertSwalHtml(values, theme = 1) {
    const _themeShowSwalAlert = returnThemeSwalAlert(theme);
    _themeShowSwalAlert.fire(values)
}

async function showAlertSwalHtmlDecision(values, theme = 1) {
    const _themeShowSwalAlert = returnThemeSwalAlert(theme);
    return await _themeShowSwalAlert.fire(values).then((result) => {
        return result;
    })
}

function returnThemeSwalAlert(op){
    switch (op) {
        case 0:
            return themeDefaultAlertSwal;
            break;
        case 1:
            return themeOneAlertSwal;
            break;            
    }
}