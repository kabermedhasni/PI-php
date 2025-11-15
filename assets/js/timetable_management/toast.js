// toast.js - Toast Notification System

export class ToastManager {
  constructor() {
    this.createToastElement();
  }
  
  createToastElement() {
    if (document.getElementById("toast-notification")) return;

    const toast = document.createElement("div");
    toast.id = "toast-notification";
    toast.className = "toast";
    document.body.appendChild(toast);
  }
  
  show(type, message) {
    const toast = document.getElementById("toast-notification");
    toast.textContent = message;
    toast.className = "toast";

    if (type === "success") {
      toast.classList.add("toast-success");
    } else if (type === "error") {
      toast.classList.add("toast-error");
    } else {
      toast.classList.add("toast-info");
    }

    toast.classList.add("show");

    setTimeout(() => {
      toast.classList.remove("show");
    }, 3000);
  }
}