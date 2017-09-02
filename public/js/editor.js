require(['bootstrap', 'jquery'], function() {
    const editButtons = document.querySelectorAll(".submission-edit");
    const editor = document.querySelector("#editor form");
    const channelField = editor.querySelector("#channel");
    const typeField = editor.querySelector("#type");

    let currentRow;
    const updateEditor = (e) => {
        currentRow = e.target.parentNode.parentNode;
        channelField.value = currentRow.cells[2].textContent;
        typeField.value = currentRow.cells[1].textContent;
    };
    const save = (e) => {
        e.preventDefault();

        const channel = channelField.value;
        const description = typeField.value;

        currentRow.cells[2].textContent = channel;
        currentRow.cells[1].textContent = description;

        const id = currentRow.querySelector('input[name="id"]').value;
        const token = currentRow.querySelector('input[name="token"]').value;

        fetch('/lib/subedit', {
            method: 'POST',
            body: {
                id,
                token,
                channel,
                description
            },
            credentials: 'same-origin'
        }).then((r) => {
            if(!r.ok) {
                throw r.status;
            }
            else {
                $('#editor').modal('hide');
            }
        }).catch(console.error);
    };

    editor.addEventListener("submit", save, false);
    for(const button of editButtons) {
        button.addEventListener("click", updateEditor, false);
    }
});
