import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'hiddenInput', 'counter'];

    connect() {
        console.log('Availability controller connected');
        console.log('Checkboxes found:', this.checkboxTargets.length);
        this.updateHiddenInput();
    }

    toggleDay(event) {
        console.log('Toggle day:', event.target.dataset.date);
        this.updateHiddenInput();
    }

    selectWeekday(event) {
        event.preventDefault();
        const weekday = parseInt(event.currentTarget.dataset.weekday);
        console.log('Select weekday:', weekday);
        const checkboxes = this.checkboxTargets;
        
        checkboxes.forEach(checkbox => {
            const date = new Date(checkbox.dataset.date);
            // getDay() gibt 0=Sonntag, 1=Montag, etc. zurück
            // Wir müssen auf ISO-Format umrechnen (1=Montag, 7=Sonntag)
            let isoWeekday = date.getDay();
            if (isoWeekday === 0) isoWeekday = 7; // Sonntag = 7
            
            if (isoWeekday === weekday) {
                checkbox.checked = true;
            }
        });
        
        this.updateHiddenInput();
    }

    deselectWeekday(event) {
        event.preventDefault();
        const weekday = parseInt(event.currentTarget.dataset.weekday);
        console.log('Deselect weekday:', weekday);
        const checkboxes = this.checkboxTargets;
        
        checkboxes.forEach(checkbox => {
            const date = new Date(checkbox.dataset.date);
            let isoWeekday = date.getDay();
            if (isoWeekday === 0) isoWeekday = 7;
            
            if (isoWeekday === weekday) {
                checkbox.checked = false;
            }
        });
        
        this.updateHiddenInput();
    }

    selectAll(event) {
        event.preventDefault();
        console.log('Select all');
        this.checkboxTargets.forEach(checkbox => {
            checkbox.checked = true;
        });
        this.updateHiddenInput();
    }

    deselectAll(event) {
        event.preventDefault();
        console.log('Deselect all');
        this.checkboxTargets.forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateHiddenInput();
    }

    updateHiddenInput() {
        const selectedDates = this.checkboxTargets
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.dataset.date);
        
        console.log('Selected dates:', selectedDates.length);
        this.hiddenInputTarget.value = JSON.stringify(selectedDates);
        
        // Update Counter
        if (this.hasCounterTarget) {
            this.counterTarget.textContent = selectedDates.length;
        }
    }
}
