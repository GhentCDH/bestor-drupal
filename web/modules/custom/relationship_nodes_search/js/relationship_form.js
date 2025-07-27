(function (Drupal, once) {
  Drupal.behaviors.relationshipForm = {
    attach(context) {
      once('relationship-checkbox', '.relationship-child-checkbox', context).forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            const currentTr = checkbox.closest('tr');
            let parentTr = currentTr.previousElementSibling;
            while (parentTr && !parentTr.querySelector('.relationship-parent-add')) {
                parentTr = parentTr.previousElementSibling;
            }

            if (!parentTr) {
                console.warn('Geen parent-knop gevonden voor checkbox');
                return;
            }
           
            const parentButton = parentTr.querySelector('.relationship-parent-add');

            let anyChecked = false;
            let nextRow = parentTr.nextElementSibling;
            while (nextRow && nextRow.querySelector('.relationship-child-checkbox')) {
                const cb = nextRow.querySelector('.relationship-child-checkbox');
                if (cb.checked) {
                anyChecked = true;
                break;
                } 
                nextRow = nextRow.nextElementSibling;
            }

            console.log(anyChecked);
            if(anyChecked){
                parentButton.disabled = false;
                parentButton.classList.remove('is-disabled');
                console.log(parentButton);
            } else {
                parentButton.disabled = true;
                parentButton.classList.add('is-disabled');
            }
        });
      });
    }
  };
})(Drupal, once);