package de.idrinth.walled_secrets;

import java.io.IOException;
import javax.json.JsonObject;
import javax.swing.Box;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JTextArea;
import javax.swing.Popup;
import javax.swing.PopupFactory;
import javax.swing.SwingWorker;

public class NoteDetailsFrame extends JFrame
{
    private final Request request;
    private final JTextArea note;
    private final String project;

    public NoteDetailsFrame(String project, Request request)
    {
        super("Note Details | " + project);
        this.project = project;
        this.request = request;
        setDefaultCloseOperation(JFrame.DISPOSE_ON_CLOSE);
        Box wrapper = Box.createVerticalBox();
        Box noteBox = Box.createHorizontalBox();
        noteBox.add(new JLabel("Your Note"));
        this.note = new JTextArea(10, 55);
        noteBox.add(this.note);
        wrapper.add(noteBox);
        add(wrapper);
        pack();
    }
    public void display(boolean visible, String id, String master) {
        setVisible(visible);
        toFront();
        setTitle("Loading | Note Details | " + project);
        JFrame a = this;
        (new SwingWorker() {
            @Override
            protected Object doInBackground() throws Exception {
                try {
                    JsonObject obj = request.getNote(id, master);
                    note.setText(obj.getString("content", ""));
                    setTitle(obj.getString("public", "") + " | Note Details | " + project);
                } catch (IOException ex) {
                    Popup popup = PopupFactory.getSharedInstance().getPopup(a, new JLabel(ex.getMessage()), 0, 0);
                    popup.show();
                }
                return null;
            }
        }).execute();
    }
}
