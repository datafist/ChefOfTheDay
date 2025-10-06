import { Controller } from '@hotwired/stimulus';

/*
 * Stimulus Controller für das Ein-/Ausblenden von Zuweisungen im Dashboard
 * 
 * Verwendung im Template:
 * <div data-controller="assignments-toggle">
 *   <button data-action="assignments-toggle#toggle">Toggle</button>
 * </div>
 */
export default class extends Controller {
    static targets = ['btn', 'showText', 'hideText', 'table'];

    connect() {
        this.isExpanded = false;
    }

    toggle() {
        const hiddenRows = document.querySelectorAll('.hidden-assignment');
        const showText = document.getElementById('btn-text-show');
        const hideText = document.getElementById('btn-text-hide');
        const btn = document.getElementById('toggle-assignments-btn');

        if (this.isExpanded) {
            // Verstecke zusätzliche Zeilen
            hiddenRows.forEach(row => {
                row.classList.add('hidden-assignment');
                row.style.display = 'none';
            });
            showText.style.display = 'inline';
            hideText.style.display = 'none';
            btn.style.backgroundColor = '#007bff';
            
            // Scrolle zum Anfang der Tabelle
            document.getElementById('assignments-table')?.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        } else {
            // Zeige alle Zeilen
            hiddenRows.forEach(row => {
                row.classList.remove('hidden-assignment');
                row.style.display = 'table-row';
            });
            showText.style.display = 'none';
            hideText.style.display = 'inline';
            btn.style.backgroundColor = '#6c757d';
        }

        this.isExpanded = !this.isExpanded;
    }
}
