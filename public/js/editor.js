require(['bootstrap', 'jquery'], function() {
    const editButtons = document.querySelectorAll(".submission-edit");
    const editor = document.querySelector("#editor form");
    const channelField = editor.querySelector("#channel");
    const typeField = editor.querySelector("#type");
    const idField = editor.querySelector('input[name="id"]');

    let currentRow;
    const updateEditor = (e) => {
        currentRow = e.target.parentNode.parentNode;
        channelField.value = currentRow.cells[2].textContent;
        typeField.value = currentRow.cells[1].textContent;
        idField.value = currentRow.querySelector('input[name="id"]').value;
        document.querySelector('#editor .channel-name').textContent = currentRow.cells[0].textContent;
    };
    const save = (e) => {
        e.preventDefault();

        const body = new FormData(editor);

        fetch('/lib/subedit', {
            method: 'POST',
            body,
            credentials: 'same-origin'
        }).then((r) => {
            if(!r.ok) {
                throw r.status;
            }
            else {
                if(channelField.value != currentRow.cells[2].textContent) {
                    currentRow.cells[2].innerHTML = "";
                    if(channelField.value.length) {
                        const link = document.createElement("a");
                        link.href = `https://twitch.tv/${channelField.value}`;
                        link.textContent = channelField.value;
                        currentRow.cells[2].appendChild(link);
                    }
                    else {
                        const icon = document.createElement("span");
                        icon.classList.add("glyphicon");
                        icon.classList.add("glyphicon-question-sign");
                        icon.classList.add("status-unknown");
                        icon.title = "No data";
                        currentRow.cells[2].appendChild(icon);
                    }

                }
                if(typeField.value != currentRow.cells[1].textContent) {
                    if(isNaN(typeField.value)) {
                        currentRow.cells[1].textContent = typeField.value;
                    }
                    else {
                        const link = document.createElement("a");
                        link.href = `/types/${typeField.value}`;
                        link.textContent = typeField.value;
                        currentRow.cells[1].innerHTML = "";
                        currentRow.cells[1].appendChild(link);
                    }
                }
                $('#editor').modal('hide');
            }
        }).catch(console.error);
    };

    editor.addEventListener("submit", save, false);
    for(const button of editButtons) {
        button.addEventListener("click", updateEditor, false);
    }
});
