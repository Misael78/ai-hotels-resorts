(function (Drupal) {
  Drupal.behaviors.WebformBooking = {
    attach: function (context, settings) {
      if (settings.webform_booking && settings.webform_booking.elements) {
        Object.keys(settings.webform_booking.elements).forEach(function (key) {
          const elementConfig = settings.webform_booking.elements[key];
          const elementId = elementConfig.elementId;
          const required = elementConfig.required;
          const formId = elementConfig.formId;
          let startDate = elementConfig.startDate || new Date().toISOString().split('T')[0];
          const endDate = elementConfig.endDate;
          const noSlots = elementConfig.noSlots ?? 'No slots available';

          const today = new Date().toISOString().split('T')[0];
          if (new Date(startDate) < new Date(today)) {
            startDate = today;
          }
          // Ensure we only attach this behavior once per calendar instance
          once('webform-booking-init', `#calendar-container-${elementId}`, context).forEach(function () {
            checkAvailableMonthsAndFetchDays(formId, elementId, startDate, endDate);

            const inputSelector = `#selected-slot-${elementId}`;
            const inputElements = document.querySelectorAll(`input${inputSelector}.required`);
            inputElements.forEach(function (inputElement) {
              inputElement.removeAttribute('required');
              inputElement.removeAttribute('aria-required');
              document.getElementById(`slots-container-${elementId}`).setAttribute('required', 'required');
            });

            const formItem = document.querySelector(`.js-form-item-${elementId}`);
            if (formItem) {
              const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                  if (mutation.attributeName === 'style') {
                    const displayStyle = formItem.style.display;
                    const slotsContainer = document.getElementById(`slots-container-${elementId}`);
                    if (displayStyle === 'block' && required) {
                      slotsContainer.setAttribute('required', 'required');
                      slotsContainer.setAttribute('aria-required', 'true');
                    } else {
                      slotsContainer.removeAttribute('required');
                      slotsContainer.removeAttribute('aria-required');
                    }
                  }
                });
              });
              observer.observe(formItem, { attributes: true, attributeFilter: ['style'] });
            }
          });

          function checkAvailableMonthsAndFetchDays(formId, elementId, startDate, endDate) {
            const currentDate = new Date();
            let start = new Date(startDate);
            let end = endDate ? new Date(endDate) : new Date(currentDate.getFullYear() + 1, currentDate.getMonth(), currentDate.getDate());

            if (start < currentDate) {
              start = currentDate;
            }

            const oneYearFromStart = new Date(start);
            oneYearFromStart.setFullYear(oneYearFromStart.getFullYear() + 1);
            if (end > oneYearFromStart) {
              end = oneYearFromStart;
            }

            const availableMonths = [];
            const requests = [];

            let currentMonth = new Date(start.getFullYear(), start.getMonth(), 1);

            while (currentMonth <= end) {
              const monthStart = formatDate(currentMonth);
              const daysUrl = `/get-days/${formId}/${elementId}/${monthStart}`;
              requests.push(
                fetch(daysUrl)
                  .then(response => {
                    if (!response.ok) {
                      throw new Error(`Failed to fetch for ${monthStart}`);
                    }
                    return response.json();
                  })
                  .then(data => {
                    return data;
                  })
                  .catch(error => {
                    console.error('Fetch error:', error);
                    return [];
                  })
              );
              availableMonths.push(monthStart);

              // Move to the first day of the next month
              currentMonth.setMonth(currentMonth.getMonth() + 1, 1);
            }

            Promise.all(requests).then(responses => {
              const filteredMonths = availableMonths.filter((monthStart, index) => {
                return responses[index].length > 0;
              });

              if (filteredMonths.length > 0) {
                const initialDate = filteredMonths[0];
                fetchDays(formId, elementId, initialDate, filteredMonths);
                fetchSlots(formId, elementId, initialDate);
              }
            });
          }

          function fetchDays(formId, elementId, date, filteredMonths) {
            const daysUrl = `/get-days/${formId}/${elementId}/${date}`;
            const calendarContainer = document.querySelector(`#calendar-container-${elementId}`);
            const currentDate = new Date(date);
            const currentYear = currentDate.getFullYear();
            const currentMonth = currentDate.getMonth();
            const slotsContainer = document.getElementById(`slots-container-${elementId}`);
            slotsContainer.removeEventListener('click', fetchSlots);

            fetch(daysUrl)
              .then(response => response.json())
              .then(function (daysData) {
                if (!daysData || daysData.length === 0) {
                  document.getElementById(`appointment-wrapper-${elementId}`).innerHTML = `<div class="no-slots-message">${noSlots}</div>`;
                  return;
                }
                calendarContainer.innerHTML = createMonthSelect(filteredMonths, date);

                let weekDaysHtml = '<div class="week-days">';
                const weekDays = [Drupal.t('Mon'), Drupal.t('Tue'), Drupal.t('Wed'), Drupal.t('Thu'), Drupal.t('Fri'), Drupal.t('Sat'), Drupal.t('Sun')];
                weekDays.forEach(function (weekDay) {
                  weekDaysHtml += `<div class="week-day">${weekDay}</div>`;
                });
                weekDaysHtml += '</div>';
                calendarContainer.innerHTML += weekDaysHtml;

                const firstDay = new Date(currentYear, currentMonth, 1).getDay();
                const emptyDays = (firstDay === 0 ? 6 : firstDay - 1);
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                let daysHtml = '<div class="calendar-days">';
                for (let i = 0; i < emptyDays; i++) {
                  daysHtml += '<div class="calendar-day empty"></div>';
                }
                for (let day = 1; day <= daysInMonth; day++) {
                  const fullDate = `${currentYear}-${(currentMonth + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                  let dayClass = 'calendar-day';
                  if (daysData.includes(fullDate)) {
                    dayClass += ' available';
                  }
                  daysHtml += `<div class="${dayClass}" data-date="${fullDate}">${day}</div>`;
                }
                daysHtml += '</div>';
                calendarContainer.innerHTML += daysHtml;
                const firstAvailableDay = document.querySelector(`#calendar-container-${elementId} .calendar-day.available`);
                if (firstAvailableDay) {
                  firstAvailableDay.classList.add('active');
                }

                const monthSelect = document.querySelector(`#month-select-${elementId}`);
                if (monthSelect) {
                  monthSelect.addEventListener('change', function () {
                    if (this.value) {
                      const selectedMonthYear = this.value.split('-');
                      const year = selectedMonthYear[0];
                      const month = selectedMonthYear[1];
                      resetSlots(elementId);
                      fetchDays(formId, elementId, `${year}-${month}-01`, filteredMonths);
                    }
                  });
                }

                const availableDays = document.querySelectorAll(`#calendar-container-${elementId} .calendar-day.available`);
                availableDays.forEach(function (day) {
                  day.addEventListener('click', function () {
                    document.querySelectorAll(`#calendar-container-${elementId} .calendar-day`).forEach(function (elem) {
                      elem.classList.remove('active');
                    });
                    this.classList.add('active');
                    const selectedDate = this.dataset.date;
                    resetSlots(elementId);
                    fetchSlots(formId, elementId, selectedDate);
                  });
                });
                const firstAvailableDate = firstAvailableDay ? firstAvailableDay.dataset.date : null;
                if (firstAvailableDate) {
                  fetchSlots(formId, elementId, firstAvailableDate);
                } else {
                  document.getElementById(`slots-container-${elementId}`).innerHTML = '';
                }
              });
          }

          function createMonthSelect(filteredMonths, selectedDate) {
            const selected = new Date(selectedDate);
            let monthSelect = `<select id="month-select-${elementId}">`;

            filteredMonths.forEach(function (monthStart) {
              const year = new Date(monthStart).getFullYear();
              const month = new Date(monthStart).getMonth();
              const optionValue = `${year}-${(month + 1).toString().padStart(2, '0')}`;
              const isSelected = year === selected.getFullYear() && month === selected.getMonth();
              const monthName = new Date(year, month).toLocaleString('default', { month: 'long' });
              monthSelect += `<option value="${optionValue}"${isSelected ? ' selected' : ''}>${monthName} ${year}</option>`;
            });

            monthSelect += '</select>';
            return monthSelect;
          }

          function fetchSlots(formId, elementId, date) {
            const slotsUrl = `/get-slots/${formId}/${elementId}/${date}`;
            const slotsContainer = document.getElementById(`slots-container-${elementId}`);
            const noSlotsMessage = `<div class="no-slots-message">${noSlots}</div>`;

            fetch(slotsUrl)
              .then(response => response.json())
              .then(function (slotsData) {
                slotsContainer.innerHTML = '';
                if (slotsData.every(slot => slot.status === 'unavailable')) {
                  slotsContainer.innerHTML = noSlotsMessage;
                } else {
                  slotsData.forEach(function (slot) {
                    if (slot.time) {
                      const slotElement = `<div class="calendar-slot ${slot.status}" data-time="${slot.time.split('-')[0]}">${slot.time}</div>`;
                      slotsContainer.innerHTML += slotElement;
                    }
                  });
                  // Trigger custom event 'webform_booking_slots_ready'
                  const event = new CustomEvent('webform_booking_slots_ready', {
                    detail: {
                      formId: formId,
                      elementId: elementId,
                      date: date
                    }
                  });

                  document.dispatchEvent(event);
                  const availableSlots = document.querySelectorAll(`#slots-container-${elementId} .calendar-slot.available`);
                  availableSlots.forEach(function (slot) {
                    slot.addEventListener('click', function () {
                      resetSlots(elementId);
                      this.classList.add('selected');
                      const time = this.dataset.time;
                      selectSlot(date, time);
                    });
                  });
                }
              });
          }

          function selectSlot(date, time) {
            document.getElementById(`selected-slot-${elementId}`).value = date + ' ' + time;
          }

          function resetSlots(elementId) {
            document.getElementById(`selected-slot-${elementId}`).value = '';
            const slots = document.querySelectorAll(`#slots-container-${elementId} .calendar-slot`);
            slots.forEach(function (slot) {
              slot.classList.remove('selected');
            });
          }
        });
      }
    }
  };
})(Drupal);

// Helper function to format date as YYYY-MM-DD
function formatDate(date) {
  const year = date.getFullYear();
  const month = (date.getMonth() + 1).toString().padStart(2, '0');
  const day = date.getDate().toString().padStart(2, '0');
  return `${year}-${month}-${day}`;
}
